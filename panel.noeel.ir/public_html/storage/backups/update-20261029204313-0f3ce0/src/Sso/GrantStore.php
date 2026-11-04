<?php
declare(strict_types=1);
namespace Babanoell\Panel\Sso;

use Babanoell\Panel\Security\TokenVault;
use RuntimeException;

/** Filesystem-backed, one-use authorization codes and independently expiring grants. */
final class GrantStore
{
    private string $dir;
    public const TARGETS = ['identity','loans','documents','partners','finance','integrations'];
    public function __construct(string $root, private readonly TokenVault $vault)
    {
        $this->dir = rtrim($root, '/').'/storage/sso';
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('SSO private directory unavailable');
        }
        @chmod($this->dir, 0700);
        if (!is_writable($this->dir)) throw new RuntimeException('SSO private directory not writable');
    }
    public static function validTarget(string $service): bool { return in_array($service, self::TARGETS, true); }
    public static function b64(string $input): string { return rtrim(strtr(base64_encode($input), '+/', '-_'), '='); }
    private function path(string $kind, string $key): string { return $this->dir.'/'.$kind.'-'.hash('sha256', $key).'.json'; }
    private function save(string $path, array $data): void
    {
        $handle = @fopen($path, 'x');
        if ($handle === false) throw new RuntimeException('SSO state store unavailable');
        @chmod($path, 0600);
        try {
            if (!flock($handle, LOCK_EX) || fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR)) === false || !fflush($handle)) {
                throw new RuntimeException('Cannot persist SSO state');
            }
        } catch (\Throwable $error) { @unlink($path); throw $error; }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    public function issue(string $service, string $state, string $challenge, string $access, string $userId): string
    {
        if (!self::validTarget($service) || !preg_match('/^[a-f0-9]{32,64}$/D', $state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge) || $access === '' || $userId === '') {
            throw new RuntimeException('Invalid SSO authorization request');
        }
        $code = self::b64(random_bytes(32));
        $this->save($this->path('code', $code), [
            'service'=>$service, 'state'=>$state, 'challenge'=>$challenge,
            'user_id'=>$userId,'credential'=>$this->vault->seal($access),
            'expires'=>time()+45,
        ]);
        return $code;
    }
    /** Atomic one-time read and destruction, including for rejected codes. */
    private function consume(string $path): ?array
    {
        $handle = @fopen($path, 'r+');
        if ($handle === false) return null;
        if (!flock($handle, LOCK_EX)) { fclose($handle); return null; }
        $raw = stream_get_contents($handle);
        rewind($handle);
        ftruncate($handle, 0);
        fflush($handle);
        @unlink($path);
        flock($handle, LOCK_UN);
        fclose($handle);
        $result = json_decode((string)$raw, true);
        return is_array($result) ? $result : null;
    }
    /** @return array{credential:string,user_id:string}|null */
    public function exchange(string $service, string $state, string $code, string $verifier): ?array
    {
        if (!self::validTarget($service) || !preg_match('/^[a-f0-9]{32,64}$/D',$state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$code)
            || !preg_match('/^[A-Za-z0-9_-]{43,128}$/D',$verifier)) return null;
        $data = $this->consume($this->path('code', $code));
        if ($data === null || (int)($data['expires']??0)<time()
            || !hash_equals((string)($data['service']??''),$service)
            || !hash_equals((string)($data['state']??''),$state)
            || !hash_equals((string)($data['challenge']??''),self::b64(hash('sha256',$verifier,true)))) return null;
        try { $access = $this->vault->open((string)$data['credential']); } catch (\Throwable) { return null; }
        if ($this->isRevoked($access)) return null;
        return ['credential'=>$access, 'user_id'=>(string)($data['user_id']??'')];
    }
    public function revokeAccess(string $access): void
    {
        if ($access === '') return;
        $path=$this->path('revoked',hash('sha256',$access));
        if (!is_file($path)) $this->save($path,['expires'=>time()+1500]);
    }
    private function isRevoked(string $access): bool
    {
        $path=$this->path('revoked',hash('sha256',$access));
        if (!is_file($path)) return false;
        $info=json_decode((string)@file_get_contents($path),true);
        if (!is_array($info)) return true; // Corrupted revocation markers fail closed.
        if ((int)($info['expires']??0)>=time()) return true;
        @unlink($path);
        return false;
    }
    public function createGrant(string $service, string $access, string $userId): array
    {
        $id = self::b64(random_bytes(24)); $secret = self::b64(random_bytes(32));
        $this->save($this->path('grant',$id), [
            'service'=>$service,'user_id'=>$userId,'secret_hash'=>hash('sha256',$secret),
            'credential'=>$this->vault->seal($access),'expires'=>time()+1200,
        ]);
        return ['id'=>$id,'secret'=>$secret,'expires_in'=>1200,'user_id'=>$userId];
    }
    /** @return array{credential:string,user_id:string}|null */
    public function getGrant(string $service, string $id, string $secret): ?array
    {
        if (!self::validTarget($service)||!preg_match('/^[A-Za-z0-9_-]{32}$/D',$id)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$secret)) return null;
        $path=$this->path('grant',$id);
        $data=json_decode((string)@file_get_contents($path),true);
        if (!is_array($data)) return null;
        if ((int)($data['expires']??0)<time()) { @unlink($path); return null; }
        if (!hash_equals((string)($data['service']??''),$service) ||
            !hash_equals((string)($data['secret_hash']??''),hash('sha256',$secret))) return null;
        try { $access=$this->vault->open((string)($data['credential']??'')); } catch (\Throwable) { return null; }
        if ($this->isRevoked($access)) return null;
        return ['credential'=>$access,'user_id'=>(string)($data['user_id']??'')];
    }
    /** User handoffs are stored separately from updater-admin grants. */
    public function issueUser(string $service, string $state, string $challenge, string $access, string $userId, string $browserId): string
    {
        if (!UserRolePolicy::target($service) || !preg_match('/^[a-f0-9]{48}$/D', $state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge) || $access === '' || $userId === '') {
            throw new RuntimeException('Invalid user SSO request');
        }
        $code = self::b64(random_bytes(32));
        $this->save($this->path('user-code', $code), [
            'service'=>$service, 'state'=>$state, 'challenge'=>$challenge,
            'user_id'=>$userId, 'browser_id'=>$browserId, 'credential'=>$this->vault->seal($access), 'expires'=>time()+45,
        ]);
        return $code;
    }
    /** @return array{credential:string,user_id:string}|null */
    public function exchangeUser(string $service, string $state, string $code, string $verifier): ?array
    {
        if (!UserRolePolicy::target($service) || !preg_match('/^[a-f0-9]{48}$/D', $state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $code)
            || !preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $verifier)) return null;
        $data = $this->consume($this->path('user-code', $code));
        if ($data === null || (int)($data['expires']??0)<time()
            || !hash_equals((string)($data['service']??''), $service)
            || !hash_equals((string)($data['state']??''), $state)
            || !hash_equals((string)($data['challenge']??''), self::b64(hash('sha256', $verifier, true)))) return null;
        try { $access = $this->vault->open((string)$data['credential']); } catch (\Throwable) { return null; }
        if ($this->isRevoked($access) || $this->browserRevoked((string)($data['browser_id']??''))) return null;
        return ['credential'=>$access, 'user_id'=>(string)($data['user_id']??''), 'browser_id'=>(string)($data['browser_id']??'')];
    }
    public function createUserGrant(string $service, string $access, string $userId, string $browserId): array
    {
        if (!UserRolePolicy::target($service) || $userId === '') throw new RuntimeException('Invalid target');
        $id=self::b64(random_bytes(24)); $secret=self::b64(random_bytes(32));
        $this->save($this->path('user-grant', $id), [
            'service'=>$service, 'user_id'=>$userId, 'browser_id'=>$browserId,
            'secret_hash'=>hash('sha256',$secret), 'credential'=>$this->vault->seal($access),
            'expires'=>time()+900,
        ]);
        return ['id'=>$id, 'secret'=>$secret, 'expires_in'=>900, 'user_id'=>$userId];
    }
    /** @return array{credential:string,user_id:string}|null */
    public function getUserGrant(string $service, string $id, string $secret): ?array
    {
        if (!UserRolePolicy::target($service) || !preg_match('/^[A-Za-z0-9_-]{32}$/D', $id)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $secret)) return null;
        $path=$this->path('user-grant', $id);
        $data=json_decode((string)@file_get_contents($path),true);
        if (!is_array($data)) return null;
        if ((int)($data['expires']??0)<time()) { @unlink($path); return null; }
        if (!hash_equals((string)($data['service']??''),$service)
            || !hash_equals((string)($data['secret_hash']??''),hash('sha256',$secret))) return null;
        try { $access=$this->vault->open((string)$data['credential']); } catch (\Throwable) { return null; }
        if ($this->isRevoked($access) || $this->browserRevoked((string)($data['browser_id']??''))) return null;
        return ['credential'=>$access, 'user_id'=>(string)($data['user_id']??'')];
    }
    public function revokeUserBrowser(string $browserId): void
    {
        if (!preg_match('/^[a-f0-9]{48}$/D',$browserId)) return;
        $path=$this->path('user-browser-revoked',$browserId);
        if (!is_file($path)) $this->save($path,['expires'=>time()+1800]);
    }
    private function browserRevoked(string $browserId): bool
    {
        if (!preg_match('/^[a-f0-9]{48}$/D',$browserId)) return true;
        $path=$this->path('user-browser-revoked',$browserId);
        if (!is_file($path)) return false;
        $data=json_decode((string)@file_get_contents($path),true);
        if (!is_array($data)) return true;
        if ((int)($data['expires']??0)>time()) return true;
        @unlink($path);return false;
    }
}
