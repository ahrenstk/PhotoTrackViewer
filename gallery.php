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

$baseDir = realpath(__DIR__);


$inputPath = $_GET['path'] ?? 'img';

$sanitizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($inputPath, '/\\'));
$requestedPath = $baseDir . ($sanitizedPath !== '' ? DIRECTORY_SEPARATOR . $sanitizedPath : '');

$resolvedPath = realpath($requestedPath);


if (
    $resolvedPath !== false && 
    is_dir($resolvedPath) && 
    ($resolvedPath === $baseDir || strpos($resolvedPath, $baseDir . DIRECTORY_SEPARATOR) === 0)
) {
    $imagePath = $resolvedPath . DIRECTORY_SEPARATOR;
} else {
    $imagePath = $baseDir . DIRECTORY_SEPARATOR;
}

function getAllImages($fullPath) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!is_dir($fullPath)) return [];

    $images = [];
    $dir = new DirectoryIterator($fullPath);
    foreach ($dir as $fileinfo) {
        if (!$fileinfo->isDot() && $fileinfo->isFile()) {
            $ext = strtolower($fileinfo->getExtension());
            if (in_array($ext, $allowed, true)) {
                $images[] = $fileinfo->getFilename();
            }
        }
    }
    return $images;
}

function serveImage($filename, $fullPath) {
    $filename = basename($filename);
    $targetFile = $fullPath . $filename;
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true) || !file_exists($targetFile)) {
        header("HTTP/1.0 404 Not Found");
        exit("Datei nicht gefunden");
    }

    $mime = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp'
    ];

    header("Content-Type: " . ($mime[$ext] ?? 'application/octet-stream'));
    header("Cache-Control: public, max-age=31536000");
    readfile($targetFile);
    exit;
}

if (isset($_GET['img'])) {
    serveImage($_GET['img'], $imagePath);
}

if (isset($_GET['getImages'])) {
    header("Content-Type: application/json");
    echo json_encode(getAllImages($imagePath));
    exit;
}

$title = basename(__DIR__);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />



<style>

html,
body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #fff;
}

.image-container {
  position: relative;
  width: 100%;
  height: 100%;
}

img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

#timer {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 1000;
  color: white;
  font-size: 96px;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

</style>


</head>
<body>

<div class="image-container">
  <img src="" id="randomImage">
</div>
<div id="timer"></div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const currentPath = urlParams.get('path') ?? 'img';

const els = {};

const state = { 
    images: [], 
    currentIndex: 0 
};

const CONFIG = {
    SLIDESHOW_INTERVAL: 4000,
    CLOCK_INTERVAL: 1000
};


function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

async function fetchAndShuffleImages() {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        
        const apiUrl = `${window.location.pathname}?getImages=true&path=${encodeURIComponent(currentPath)}`;
        const response = await fetch(apiUrl, { 
            signal: controller.signal, 
            cache: 'no-cache' 
        });
        
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const newImages = await response.json();
        
        if (Array.isArray(newImages) && newImages.length > 0) {
            state.images = shuffleArray(newImages);
            state.currentIndex = 0;
            refreshImage();
        } else {
            console.warn('Keine Bilder im Verzeichnis gefunden.');
        }
    } catch (error) {
        if (error.name !== 'AbortError') { 
            console.error('Fehler beim Laden der Bildliste:', error); 
        }
    }
}

function getNextImage() {
    if (state.images.length === 0) return '';
    const image = state.images[state.currentIndex];
    state.currentIndex = (state.currentIndex + 1) % state.images.length;
    return image;
}

function refreshImage() {
    const nextImgName = getNextImage();
    if (!nextImgName) return;

    const newSrc = `${window.location.pathname}?img=${encodeURIComponent(nextImgName)}&path=${encodeURIComponent(currentPath)}`;


    const imgLoader = new Image();
    imgLoader.onload = () => {
        els.randomImage.src = newSrc;
    };
    imgLoader.src = newSrc;
}


function updateTimer() {
    const tick = () => {
        els.timer.textContent = new Date().toLocaleTimeString('de-DE');
    };
    tick();
    setInterval(tick, CONFIG.CLOCK_INTERVAL);
}

document.addEventListener('DOMContentLoaded', () => {
    els.randomImage = document.getElementById('randomImage');
    els.timer = document.getElementById('timer');

    fetchAndShuffleImages();
    setInterval(refreshImage, CONFIG.SLIDESHOW_INTERVAL);
    updateTimer();
});




</script>
</body>
</html>







