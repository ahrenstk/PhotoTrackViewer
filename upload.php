<?php
/* -----------------------------------------------------------------------------

MIT License

Copyright (c) 2026 Heinz Ahrens https://github.com/ahrenstk/PhotoTrackViewer

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

----------------------------------------------------------------------------- */

setlocale(LC_ALL, 'de_DE.UTF-8');

function err($code, $msg) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['error' => ['code' => intval($code), 'msg' => $msg]]);
    exit;
}

function toBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1] ?? '');
    $val = (int)$val;
    switch ($last) {
        case 'g': $val *= 1024 ** 3; break;
        case 'm': $val *= 1024 ** 2; break;
        case 'k': $val *= 1024; break;
    }
    return $val;
}


function processAllImages() {
    $sourceDir = __DIR__ . '/img';
    $response = ['success' => true, 'count' => 0, 'errors' => []];
    if (!is_dir($sourceDir)) return ['success' => false, 'error' => 'Ordner fehlt'];

    $files = scandir($sourceDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (is_file($sourceDir . '/' . $file)) {
            $result = processImage($file);
            if ($result['success']) {
                $response['count']++;
            } else {
                $response['errors'][] = $file;
            }
        }
    }
    return $response;
}

function processImage($fileName) {
    $sourcePath = __DIR__ . '/img/' . $fileName;
    $destPath   = __DIR__ . '/min/' . $fileName;
    $targetWidth = 100;

    if (!class_exists('Imagick')) return ['success' => false, 'error' => 'Imagick fehlt'];
    if (!file_exists($sourcePath)) return ['success' => false, 'error' => 'Datei fehlt'];

    try {
        $img = new Imagick($sourcePath);
        $profiles = $img->getImageProfiles("exif", true);
        
        $img->autoOrient();
        $width = $img->getImageWidth();
        if ($width > $targetWidth) {
            $newHeight = (int) round($img->getImageHeight() * ($targetWidth / $width));
            $img->resizeImage($targetWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        }

        if (!empty($profiles)) {
            $img->profileImage('exif', $profiles['exif']);
        }

        if (in_array(strtolower($img->getImageFormat()), ['jpeg', 'jpg'])) {
            $img->setImageCompressionQuality(90);
        }
        $img->writeImage($destPath);
        $img->clear();
        $img->destroy();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}



$uploadDir = __DIR__ . '/img/';
$gpxDir    = __DIR__ . '/gpx/';
$minDir    = __DIR__ . '/min/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($gpxDir)) mkdir($gpxDir, 0755, true);
if (!is_dir($minDir)) mkdir($minDir, 0755, true);

if (!isset($_COOKIE['_sfm_xsrf'])) {
    $token = bin2hex(random_bytes(16));
    setcookie('_sfm_xsrf', $token, ['httponly' => false, 'samesite' => 'Lax']);
    $_COOKIE['_sfm_xsrf'] = $token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['xsrf']) || $_POST['xsrf'] !== ($_COOKIE['_sfm_xsrf'] ?? '')) {
        err(403, 'Invalid XSRF token');
    }
}

if (isset($_FILES['file_data'])) {
    $fileName = $_FILES['file_data']['name'];
    $fileTmp  = $_FILES['file_data']['tmp_name'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gpx'];

    if (!in_array($ext, $allowedExtensions)) {
        err(400, 'File extension not allowed');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    $allowedMimes = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gpx'  => [
            'text/xml', 
            'application/xml', 
            'application/gpx+xml', 
            'application/x-gpx+xml', 
            'text/plain', 
            'text/gpx',
            'text/xml; charset=utf-8'
        ]
    ];

    if (!isset($allowedMimes[$ext]) || !in_array($mimeType, $allowedMimes[$ext])) {
        err(400, 'Invalid file content/type');
    }

    $folder = $_POST['folder'] ?? 'img';
    $allowedFolders = ['img', 'gpx', 'min'];
    if (!in_array($folder, $allowedFolders)) $folder = 'img';

    $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($fileName));
    $targetPath = __DIR__ . "/$folder/" . $cleanName;

    $maxUploadBytes = min(toBytes(ini_get('post_max_size')), toBytes(ini_get('upload_max_filesize')));
    if ($_FILES['file_data']['size'] > $maxUploadBytes) {
        err(400, 'File too large');
    }

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        err(500, 'Error while saving file');
    }

    if ($folder === 'img') {
        processImage($cleanName);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'file' => $cleanName]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update_thumbnails') {
    header('Content-Type: application/json');
    echo json_encode(processAllImages());
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
   <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Upload</title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 15px; margin: 0; background: #f9f9f9; display: flex; flex-direction: column; align-items: center; overflow-x: hidden; }
        .button-container, .info-list-container, #status_message, #prefix_container, #file_drop_target, #upload_progress { width: 90%; max-width: 600px; margin-bottom: 15px; }
        .button-container { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: nowrap; width: 100%; }
        .button { font-size: 1.1em; font-weight: bold; border: none; border-radius: 6px; padding: 10px 15px; cursor: pointer; text-decoration: none; text-align: center; color: white; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); flex: 1 1 0px; min-width: 80px; white-space: normal; line-height: 1.2; display: flex; align-items: center; justify-content: center; }
        #mapBtn { background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%); }
        #updateBtn { background: linear-gradient(90deg, #3498db 0%, #217dbb 100%); }
        .upload-label { font-size: 1.2em; font-weight: bold; color: #999; letter-spacing: 1px; text-transform: uppercase; text-align: center; }
        .info-list { margin: 0; padding-left: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 5px 15px; }
        .info-list li { color: #666; font-size: 0.85em; line-height: 1.3; }
        #filename_prefix { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 1.1em; }
        #file_drop_target { min-height: 160px; padding: 30px 15px; border: 4px dashed #222; background: white; border-radius: 6px; font-size: 1.2em; font-weight: bold; text-align: center; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        #file_drop_target.dragover { border-color: #3498db; color: #3498db; }
        #status_message { padding: 10px; border-radius: 6px; text-align: center; font-weight: bold; display: none; }
        .status-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-entry { background: white; padding: 10px; margin: 5px 0; border-radius: 6px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); }
        .upload-progress { height: 8px; background: #eee; border-radius: 4px; overflow: hidden; }
        .progress-bar { height: 100%; background: #3498db; width: 0%; transition: width 0.3s; }
        .upload-status { display: flex; justify-content: space-between; width: 100%; margin-bottom: 5px; font-size: 0.9em; }
        .status-text { text-align: right; font-weight: bold; }
      </style>
      <script src="js/pica.min.js"></script>
      <script src="js/piexif.js"></script>
   </head>
   <body>
      <div class="button-container">
         <a href="./" class="button" id="mapBtn">Main page</a>
         <span class="upload-label">Upload</span>
         <button type="button" class="button" id="updateBtn">New Thumbnails</button>
      </div>

      <div id="status_message"></div>
      <div id="prefix_container">
         <input type="text" id="filename_prefix" placeholder="File prefix for uploads...">
      </div>
      <div id="file_drop_target" tabindex="0">Click or drag files here to upload</div>
      <input type="file" id="file_input" multiple style="display:none;" accept=".jpg,.jpeg,.png,.gpx">
      <div id="upload_progress"></div>

      <div class="info-list-container">
         <ul class="info-list">
            <li><strong>Smart Resizing:</strong> All images are saved to <span class="folder-label">img/</span>, large files resized.</li>
            <li><strong>Automatic Thumbnails:</strong> 100px previews are generated instantly to <span class="folder-label">min/</span>.</li>
            <li><strong>GPX Storage:</strong> GPS track files are automatically moved to <span class="folder-label">gpx/</span>.</li>
            <li><strong>GPS Data:</strong> EXIF location metadata is preserved for JPEG files.</li>
            <li><strong>Organization:</strong> Add custom prefixes to group files.</li>
            <li><strong>Manual Processing:</strong> Use <strong>"New Thumbnails"</strong> to generate thumbnails manually.</li>
         </ul>
      </div>

      <script>
         const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gpx'];
         const MAX_UPLOAD_SIZE = 100 * 1024 * 1024;

         const els = {
             statusMessage: document.getElementById('status_message'),
             updateBtn: document.getElementById('updateBtn'),
             dropTarget: document.getElementById('file_drop_target'),
             fileInput: document.getElementById('file_input'),
             prefixInput: document.getElementById('filename_prefix'),
             uploadProgress: document.getElementById('upload_progress')
         };

         function getXsrfToken() {
             return (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || [])[2] || '';
         }

         function showStatus(text, isSuccess = true) {
             els.statusMessage.textContent = text;
             els.statusMessage.className = isSuccess ? 'status-success' : 'status-error';
             els.statusMessage.style.display = 'block';
             setTimeout(() => { els.statusMessage.style.display = 'none'; }, 5000);
         }

         function handleFiles(files) {
             const rawPrefix = els.prefixInput.value.trim();
             const prefix = rawPrefix.replace(/[^a-z0-9_-]/gi, '_');

             Array.from(files).forEach(file => {
                 const ext = file.name.split('.').pop().toLowerCase();
                 if (!ALLOWED_EXTENSIONS.includes(ext) || file.size > MAX_UPLOAD_SIZE) return;

                 const newName = prefix ? `${prefix}_${file.name}` : file.name;
                 const renamedFile = new File([file], newName, { type: file.type });
                 const entry = createUploadEntry(renamedFile);

                 if (ext === 'gpx') {
                     uploadFile(renamedFile, entry, 'gpx');
                 } else {
                     resizeAndUpload(renamedFile, entry);
                 }
             });
         }

         function createUploadEntry(file) {
             const entry = document.createElement('div');
             entry.className = 'upload-entry';
             entry.innerHTML = `<div class="upload-status"><span>${file.name}</span><span class="status-text">0%</span></div>
                                 <div class="upload-progress"><div class="progress-bar"></div></div>`;
             els.uploadProgress.appendChild(entry);
             return entry;
         }

         async function resizeAndUpload(file, entry) {
             try {
                 const resized = await resizeImage(file);
                 await uploadFile(resized, entry, 'img');
             } catch (e) {
                 const statusElem = entry.querySelector('.status-text');
                 statusElem.textContent = 'Error';
                 statusElem.style.color = '#e74c3c';
             }
         }

         function uploadFile(file, entry, folder) {
             return new Promise((resolve, reject) => {
                 const formData = new FormData();
                 formData.append('file_data', file);
                 formData.append('folder', folder);
                 formData.append('xsrf', getXsrfToken());

                 const xhr = new XMLHttpRequest();
                 xhr.open('POST', window.location.href);
                 xhr.upload.onprogress = e => {
                     if (e.lengthComputable) {
                         const p = Math.round((e.loaded / e.total) * 100);
                         entry.querySelector('.progress-bar').style.width = p + '%';
                         entry.querySelector('.status-text').textContent = p + '%';
                     }
                 };
                 xhr.onload = () => {
                     const statusElem = entry.querySelector('.status-text');
                     const progressBar = entry.querySelector('.progress-bar');
                     try {
                         const res = JSON.parse(xhr.responseText);
                         if (xhr.status === 200 && res.success) {
                             statusElem.textContent = 'Done';
                             statusElem.style.color = '#2ecc71';
                             progressBar.style.backgroundColor = '#2ecc71';
                         } else {
                             statusElem.textContent = 'Error';
                             statusElem.style.color = '#e74c3c';
                             progressBar.style.backgroundColor = '#e74c3c';
                         }
                     } catch(e) {
                         statusElem.textContent = 'Error';
                         statusElem.style.color = '#e74c3c';
                         progressBar.style.backgroundColor = '#e74c3c';
                     }
                     resolve();
                 };
                 xhr.onerror = reject;
                 xhr.send(formData);
             });
         }

         async function resizeImage(file) {
             const MAX_WIDTH = 1000;
             const picaInstance = pica();
             const isJpeg = ['image/jpeg', 'image/jpg'].includes(file.type.toLowerCase());

             return new Promise((resolve, reject) => {
                 const reader = new FileReader();
                 reader.onload = e => {
                     const originalDataURL = e.target.result;
                     let exif = null;

                     if (isJpeg) {
                         try {
                             exif = piexif.load(originalDataURL);
                         } catch (err) {}
                     }

                     const img = new Image();
                     img.onload = async () => {
                         let targetW = img.width;
                         let targetH = img.height;

                         if (targetW > MAX_WIDTH) {
                             targetH = Math.round(img.height * (MAX_WIDTH / img.width));
                             targetW = MAX_WIDTH;
                         }

                         const canvas = document.createElement('canvas');
                         canvas.width = targetW;
                         canvas.height = targetH;

                         await picaInstance.resize(img, canvas, {
                             features: ['js', 'wasm', 'ww'],
                             unsharpAmount: 20,
                             unsharpRadius: 0.5,
                             unsharpThreshold: 2
                         });

                         const outputType = isJpeg ? 'image/jpeg' : 'image/png';

                         canvas.toBlob(async blob => {
                             if (!isJpeg || !exif) {
                                 resolve(new File([blob], file.name, { type: outputType }));
                                 return;
                             }

                             const r2 = new FileReader();
                             r2.onload = e2 => {
                                 let finalData = e2.target.result;
                                 try {
                                     const exifStr = piexif.dump(exif);
                                     finalData = piexif.insert(exifStr, e2.target.result);
                                 } catch (insertErr) {
                                     console.error("GPS EXIF Error:", insertErr);
                                 }
                                 const finalBlob = dataURLtoBlob(finalData);
                                 resolve(new File([finalBlob], file.name, { type: 'image/jpeg' }));
                             };
                             r2.readAsDataURL(blob);
                         }, outputType, 0.85);
                     };
                     img.src = originalDataURL;
                 };
                 reader.readAsDataURL(file);
             });
         }

         function dataURLtoBlob(url) {
             const parts = url.split(','), mime = parts[0].match(/:(.*?);/)[1], b = atob(parts[1]);
             let n = b.length, u = new Uint8Array(n);
             while(n--) u[n] = b.charCodeAt(n);
             return new Blob([u], {type: mime});
         }
         
         
                 els.updateBtn.addEventListener('click', () => {
             showStatus('Processing images...', true);
             const formData = new FormData();
             formData.append('action', 'update_thumbnails');
             formData.append('xsrf', getXsrfToken());

             fetch(window.location.href, { method: 'POST', body: formData })
             .then(r => r.json())
             .then(data => {
                 if(data.success) {
                     showStatus(`Success! ${data.count} thumbnails updated.`, true);
                 } else {
                     showStatus('Error: ' + (data.error || 'Unknown'), false);
                 }
             })
             .catch(() => showStatus('Network error', false));
         });

         els.dropTarget.addEventListener('click', () => els.fileInput.click());
         els.dropTarget.addEventListener('dragover', e => { e.preventDefault(); e.currentTarget.classList.add('dragover'); });
         els.dropTarget.addEventListener('dragleave', e => e.currentTarget.classList.remove('dragover'));
         els.dropTarget.addEventListener('drop', e => { 
             e.preventDefault(); 
             e.currentTarget.classList.remove('dragover'); 
             handleFiles(e.dataTransfer.files); 
         });
         els.fileInput.addEventListener('change', e => handleFiles(e.target.files)); 
         
         
      </script>
   </body>
</html>
