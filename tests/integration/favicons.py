#!/usr/bin/env python3
"""Verify local favicon bytes, cache/security behavior and browser lifecycle on disposable services."""
from __future__ import annotations
import argparse
import base64
import hashlib
import json
from pathlib import Path
import urllib.error
import urllib.request
from playwright.sync_api import sync_playwright
from stack import Stack


def audit(stack: Stack, report: dict, chromium: str = '/usr/bin/chromium') -> None:
    checks = report.setdefault('checks', [])
    def check(ok: bool, description: str) -> None:
        checks.append({'check': description, 'passed': bool(ok)})
        if not ok:
            raise AssertionError(description)

    root = Path(__file__).resolve().parents[2]
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    files = {'favicon.ico': ('image/x-icon', root/'ui/favicons/favicon.ico'),
             'favicon.gif': ('image/gif', root/'ui/favicons/favicon.gif'),
             'favicon-navy.png': ('image/png', root/'ui/favicons/favicon-navy.png'),
             'favicon-pale.png': ('image/png', root/'ui/favicons/favicon-pale.png'),
             'apple-touch-icon.png': ('image/png', root/'ui/favicons/apple-touch-icon.png'),
             'favicon.js': ('application/javascript', root/'panel.noeel.ir/public_html/public/assets/favicon.js')}
    embedded = ['data:image/png;base64,'+base64.b64encode((root/'ui/favicons'/name).read_bytes()).decode()
                for name in ['favicon-navy.png', 'favicon-pale.png']]
    for service, base in stack.urls.items():
        for name, (mime, source) in files.items():
            with opener.open(base+'/ui-asset.php?file='+name) as response:
                body = response.read()
                check(body == source.read_bytes() and response.headers.get_content_type() == mime,
                      service+': exact local bytes and MIME '+name)
                etag = response.headers['ETag']
                check(etag == '"'+hashlib.sha256(body).hexdigest()+'"', service+': content ETag '+name)
                check('max-age=86400' in response.headers['Cache-Control'], service+': cache '+name)
            try:
                opener.open(urllib.request.Request(base+'/ui-asset.php?file='+name, headers={'If-None-Match': etag}))
                check(False, service+': unchanged icon must return 304 '+name)
            except urllib.error.HTTPError as error:
                check(error.code == 304, service+': conditional 304 '+name)
        with opener.open(base+'/favicon.ico') as response:
            check(response.read() == files['favicon.ico'][1].read_bytes(), service+': root icon without authentication')
        for name in ('../config/local.env.php', 'favicon.ico.json', 'arbitrary.png'):
            try:
                opener.open(base+'/ui-asset.php?file='+name)
                check(False, service+': reject '+name)
            except urllib.error.HTTPError as error:
                check(error.code == 404, service+': rejects unlisted/private '+name)

    errors = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(executable_path=chromium, args=['--no-sandbox'])
        context = browser.new_context(reduced_motion='no-preference')
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        for service, base in stack.urls.items():
            response = page.goto(base+('/login' if service == 'panel' else '/update.php'), wait_until='load')
            page.evaluate('document.fonts.ready')
            check(response.status == 200, service+': HTML entry is healthy')
            check(page.locator('link[data-bn-favicon]').count() == 1, service+': one animated icon in the head')
            with opener.open(base+('/login' if service == 'panel' else '/update.php')) as html:
                check(html.read().count(b'rel="icon"') == 3, service+': server HTML contains GIF, PNG and ICO fallbacks')
            check(page.locator('link[rel="apple-touch-icon"]').count() == 1, service+': touch icon')
            page.wait_for_function("document.querySelector('link[data-bn-favicon]').type === 'image/png'")
            check(page.locator('link[rel="icon"]').count() == 1, service+': static candidates cannot override the active animated icon')
            check(True, service+': PNG animation initializes under the page CSP')

        base = stack.urls['panel']
        page.goto(base+'/login', wait_until='load')
        requests = []
        page.on('request', lambda request: requests.append(request.url) if 'favicon' in request.url else None)
        page.evaluate("""() => {
          window.faviconChanges=[];
          new MutationObserver(() => window.faviconChanges.push(document.querySelector('link[data-bn-favicon]').getAttribute('href')))
            .observe(document.querySelector('link[data-bn-favicon]'), {attributes:true, attributeFilter:['href']});
        }""")
        page.wait_for_timeout(1700)
        changes = page.evaluate('window.faviconChanges')
        check(len(changes) >= 2 and all(changes[n] != changes[n-1] for n in range(1, len(changes))),
              'PNG frames alternate repeatedly')
        check(all(url in embedded for url in changes), 'Animation reuses only the two original PNG payloads')
        check(not requests, 'Animation does not create recurring network requests after image preload')

        page.emulate_media(reduced_motion='reduce')
        page.wait_for_timeout(100)
        first = page.locator('link[data-bn-favicon]').get_attribute('href')
        page.wait_for_timeout(700)
        check(first == embedded[0] and first == page.locator('link[data-bn-favicon]').get_attribute('href'),
              'Reduced motion freezes the navy frame')
        page.emulate_media(reduced_motion='no-preference')
        page.evaluate("window.dispatchEvent(new Event('pagehide'))")
        stopped = page.locator('link[data-bn-favicon]').get_attribute('href')
        page.wait_for_timeout(650)
        check(stopped == page.locator('link[data-bn-favicon]').get_attribute('href'), 'pagehide stops the timer')
        page.evaluate("window.dispatchEvent(new Event('pageshow'))")
        page.wait_for_timeout(600)
        check(embedded[1] == page.locator('link[data-bn-favicon]').get_attribute('href'), 'pageshow resumes animation')

        # The authenticated Panel shell uses a different renderer from the login page.
        page.goto(base+'/login', wait_until='load')
        page.locator('[name=identifier]').fill(stack.accounts['super_admin']['email'])
        page.locator('[name=password]').fill(stack.password)
        with page.expect_navigation(wait_until='load'):
            page.locator('[name=password]').press('Enter')
        check(page.url.endswith('/dashboard') and page.locator('link[data-bn-favicon]').count() == 1,
              'Authenticated panel shell includes the same favicon')
        check(not errors, 'All inspected pages have no JavaScript exceptions')
        context.close()

        context = browser.new_context(java_script_enabled=False, reduced_motion='no-preference')
        page = context.new_page()
        page.goto(base+'/login', wait_until='load')
        check(page.locator('link[data-bn-favicon]').get_attribute('type') == 'image/gif'
              and page.locator('link[rel="icon"][type="image/x-icon"]').count() == 1,
              'Without JavaScript, native GIF and static ICO remain available')
        context.close()

        context = browser.new_context(reduced_motion='no-preference')
        page = context.new_page()
        damaged = files['favicon.js'][1].read_text().replace(embedded[1], 'data:image/png;base64,AA==')
        page.route('**/*file=favicon.js*', lambda route: route.fulfill(body=damaged, content_type='application/javascript'))
        page.goto(base+'/login', wait_until='load')
        page.wait_for_timeout(650)
        check(page.locator('link[data-bn-favicon]').get_attribute('type') == 'image/gif',
              'Failed PNG preload preserves the native GIF fallback')
        context.close()
        browser.close()
    report['complete'] = True


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path, required=True)
    parser.add_argument('--chromium', default='/usr/bin/chromium')
    args = parser.parse_args()
    stack = Stack(Path(__file__).resolve().parents[2])
    report = {'complete': False, 'scope': 'Disposable synthetic services; Chromium HTML/assets/lifecycle, not browser tab-chrome or Safari acceptance.'}
    try:
        stack.prepare()
        stack.start()
        audit(stack, report, args.chromium)
    finally:
        stack.close()
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    print('Favicon checks passed:', len(report['checks']))
