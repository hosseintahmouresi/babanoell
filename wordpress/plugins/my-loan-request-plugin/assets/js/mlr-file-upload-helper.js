/**
 * MLR File Upload Helper
 * - فشرده‌سازی تصویر با حفظ خوانایی متن (کیفیت بالا)
 * - آپلود تکی با نوار پیشرفت
 * - آپلود تکه‌ای برای فایل‌های بزرگ
 */
(function(global) {
    'use strict';

    var MAX_FILE_SIZE_MB = 10;
    var MAX_FILE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
    var MAX_ORIGINAL_IMAGE_BYTES = 25 * 1024 * 1024;
    var CHUNK_SIZE = 1024 * 1024; // 1MB per chunk; use chunked if file > 2MB
    var CHUNK_THRESHOLD = 2 * 1024 * 1024;
    var IMAGE_MAX_WIDTH = 1920;
    var IMAGE_QUALITY = 0.92;
    var DOCUMENT_FLOW_STATE_ALIASES = {
        'defect_documents': 'defect_documents',
        'نقص مدارک': 'defect_documents',
        'completion_documents': 'completion_documents',
        'تکمیل مدارک': 'completion_documents',
        'pending_documents': 'pending_documents',
        'در انتظار مدارک': 'pending_documents',
        'در انتظار تکمیل': 'pending_documents',
        'در انتظار تکمیل مدارک': 'pending_documents',
        'initial_documents': 'initial_documents'
    };
    var ALLOWED_DOCUMENT_UPLOAD_STATES = [
        '',
        'initial_documents',
        'defect_documents',
        'completion_documents',
        'pending_documents'
    ];

    function normalizeDocumentFlowState(state) {
        if (!state) {
            return '';
        }

        state = String(state).trim();
        return DOCUMENT_FLOW_STATE_ALIASES[state] || state;
    }

    function isAllowedDocumentUploadState(state) {
        return ALLOWED_DOCUMENT_UPLOAD_STATES.indexOf(normalizeDocumentFlowState(state)) !== -1;
    }

    function appendDocumentUploadState(form, opts) {
        opts = opts || {};

        var requestId = opts.request_id || opts.requestId || '';
        var state = normalizeDocumentFlowState(
            opts.document_flow_state ||
            opts.documentFlowState ||
            opts.request_status ||
            opts.status ||
            ''
        );

        if (requestId) {
            form.append('request_id', requestId);
        }

        if (state) {
            form.append('document_flow_state', state);
        }

        var portalContext = String(opts.portal_context || opts.portalContext || 'auto').trim();
        if (portalContext) {
            form.append('portal_context', portalContext);
        }

        // Preserve the site context for multisite installations. The guarantor
        // final-save handler already supports blog_id; staged uploads must use
        // the same site's uploads directory/table context too.
        var blogId = parseInt(opts.blog_id || opts.blogId || 0, 10);
        if (blogId > 0) {
            form.append('blog_id', blogId);
        }
    }

    function compressImage(file) {
        return new Promise(function(resolve, reject) {
            if (!file.type || file.type.indexOf('image/') !== 0) {
                resolve(file);
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var w = img.width, h = img.height;
                    if (w <= IMAGE_MAX_WIDTH && h <= IMAGE_MAX_WIDTH && file.size <= MAX_FILE_BYTES) {
                        resolve(file);
                        return;
                    }
                    var scale = Math.min(1, IMAGE_MAX_WIDTH / Math.max(w, h));
                    var cw = Math.max(1, Math.round(w * scale)), ch = Math.max(1, Math.round(h * scale));
                    var canvas = document.createElement('canvas');
                    canvas.width = cw;
                    canvas.height = ch;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, cw, ch);
                    var outputType = file.type === 'image/png' ? 'image/png' : (file.type === 'image/webp' ? 'image/webp' : 'image/jpeg');
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            resolve(new File([blob], file.name, { type: blob.type || outputType }));
                        } else {
                            resolve(file);
                        }
                    }, outputType, IMAGE_QUALITY);
                };
                img.onerror = function() { resolve(file); };
                img.src = e.target.result;
            };
            reader.onerror = function() { resolve(file); };
            reader.readAsDataURL(file);
        });
    }

    function uploadSingle(blobOrFile, opts, onProgress) {
        var form = new FormData();
        form.append('action', 'mlr_upload_single_file');
        form.append('nonce', opts.nonce);
        form.append('context', opts.context);
        form.append('field_key', opts.field_key || '');
        form.append('input_name', 'file');
        form.append('file', blobOrFile);
        appendDocumentUploadState(form, opts);

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable && typeof onProgress === 'function') {
                    var pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
                    onProgress(pct);
                }
            });
            xhr.addEventListener('load', function() {
                if (xhr.status !== 200) {
                    reject(new Error('خطای شبکه (کد ' + xhr.status + '). صفحه را رفرش کرده و دوباره تلاش کنید.'));
                    return;
                }
                var raw = typeof xhr.responseText === 'string' ? xhr.responseText.trim() : '';
                var json = null;
                try {
                    json = JSON.parse(raw);
                } catch (e) {
                    var start = raw.indexOf('{');
                    if (start !== -1) {
                        var end = raw.lastIndexOf('}') + 1;
                        if (end > start) try { json = JSON.parse(raw.slice(start, end)); } catch (e2) {}
                    }
                    if (!json) {
                        reject(new Error('پاسخ سرور نامعتبر است. لطفاً صفحه را رفرش کنید.'));
                        return;
                    }
                }
                if (json.success && json.data && json.data.url) {
                    resolve({ url: json.data.url, token: json.data.token || '', type: json.data.type || '' });
                } else {
                    reject(new Error(json.data && json.data.message ? json.data.message : 'خطا در آپلود'));
                }
            });
            xhr.addEventListener('error', function() { reject(new Error('خطای ارتباط با سرور')); });
            xhr.open('POST', opts.ajax_url);
            xhr.send(form);
        });
    }

    function uploadChunked(file, opts, onProgress) {
        var totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        var uploadId = 'mlr_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
        function sendChunk(index) {
            var start = index * CHUNK_SIZE;
            var end = Math.min(start + CHUNK_SIZE, file.size);
            var chunk = file.slice(start, end);

            var form = new FormData();
            form.append('action', 'mlr_upload_chunk');
            form.append('nonce', opts.nonce);
            form.append('context', opts.context || 'loan_request');
            form.append('field_key', opts.field_key || '');
            form.append('upload_id', uploadId);
            form.append('chunk_index', index);
            form.append('total_chunks', totalChunks);
            form.append('total_size', file.size);
            form.append('original_name', file.name);
            form.append('chunk', chunk);
            appendDocumentUploadState(form, opts);

            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable && typeof onProgress === 'function') {
                        var chunkProgress = (e.loaded / e.total) * (100 / totalChunks);
                        var totalSoFar = (index / totalChunks) * 100 + chunkProgress;
                        onProgress(Math.min(100, Math.round(totalSoFar)));
                    }
                });
                xhr.addEventListener('load', function() {
                    if (xhr.status !== 200) {
                        reject(new Error('خطای شبکه (کد ' + xhr.status + ')'));
                        return;
                    }
                    var raw = typeof xhr.responseText === 'string' ? xhr.responseText.trim() : '';
                    var json = null;
                    try {
                        json = JSON.parse(raw);
                    } catch (e) {
                        var start = raw.indexOf('{');
                        if (start !== -1) {
                            var end = raw.lastIndexOf('}') + 1;
                            if (end > start) try { json = JSON.parse(raw.slice(start, end)); } catch (e2) {}
                        }
                        if (!json) {
                            reject(new Error('پاسخ سرور نامعتبر است'));
                            return;
                        }
                    }
                    if (json.success) {
                        if (json.data && json.data.url) {
                            if (typeof onProgress === 'function') onProgress(100);
                            resolve({ url: json.data.url, token: json.data.token || '', type: json.data.type || '' });
                        } else {
                            resolve({ next: index + 1 });
                        }
                    } else {
                        reject(new Error(json.data && json.data.message ? json.data.message : 'خطا در آپلود تکه'));
                    }
                });
                xhr.addEventListener('error', function() { reject(new Error('خطای ارتباط با سرور')); });
                xhr.open('POST', opts.ajax_url);
                xhr.send(form);
            });
        }

        function run(index) {
            return sendChunk(index).then(function(result) {
                if (result.url) return result;
                if (result.next < totalChunks) return run(result.next);
                return Promise.reject(new Error('آپلود تکه‌ها تکمیل نشد'));
            });
        }

        return run(0);
    }

    function processAndUploadFile(file, opts, onProgress) {
        opts = opts || {};
        var state = opts.document_flow_state || opts.documentFlowState || opts.request_status || opts.status || '';
        if (!isAllowedDocumentUploadState(state)) {
            return Promise.reject(new Error('وضعیت بارگذاری مدارک نامعتبر است.'));
        }

        var isImage = file.type && file.type.indexOf('image/') === 0;
        if (isImage && file.size > MAX_ORIGINAL_IMAGE_BYTES) {
            return Promise.reject(new Error('حجم تصویر اولیه بیش از حد مجاز است.'));
        }
        if (!isImage && file.size > MAX_FILE_BYTES) {
            return Promise.reject(new Error('حداکثر حجم هر فایل ' + MAX_FILE_SIZE_MB + ' مگابایت است.'));
        }

        return compressImage(file).then(function(processed) {
            if (processed.size > MAX_FILE_BYTES) {
                throw new Error('حجم فایل پس از پردازش همچنان بیشتر از ' + MAX_FILE_SIZE_MB + ' مگابایت است.');
            }
            var useChunked = processed.size > CHUNK_THRESHOLD;
            if (useChunked) {
                return uploadChunked(processed, opts, onProgress);
            }
            return uploadSingle(processed, opts, onProgress);
        });
    }

    global.MLRFileUpload = {
        MAX_FILE_BYTES: MAX_FILE_BYTES,
        MAX_FILE_SIZE_MB: MAX_FILE_SIZE_MB,
        compressImage: compressImage,
        uploadSingle: uploadSingle,
        uploadChunked: uploadChunked,
        processAndUploadFile: processAndUploadFile,
        normalizeDocumentFlowState: normalizeDocumentFlowState,
        isAllowedDocumentUploadState: isAllowedDocumentUploadState
    };
})(typeof window !== 'undefined' ? window : this);
