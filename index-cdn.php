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

function getImageList() {
    $images = glob("min/*.{jpg,jpeg,JPG,JPEG}", GLOB_BRACE);
    return array_values(array_map('basename', $images));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? null) === 'getImages') {
    header('Content-Type: application/json');
    echo json_encode(getImageList());
    exit;
}

$gpxFiles = array_map(function($file) {
    return rawurlencode(basename($file));
}, glob("gpx/*.gpx"));

$currentDir = basename(__DIR__);
$title = $currentDir;
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>
         <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
      </title>
      <style>
         body,
         html,
         #map {
            height: 100%;
            margin: 0;
            padding: 0;
         }
         #map {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
         }
         .thumbnail-marker img {
            display: block;
            border-radius: 5px;
            border: 1px solid #666;
            contain: paint;
            transform: translateZ(0);
            user-select: none;
            pointer-events: none;
         }
         #modalOverlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
         }
         #modalFilename {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            text-align: center;
         }
         #modalFilename svg {
            height: 1.5em;
            width: 1.5em;
            fill: #000000;
            cursor: pointer;
            transition: transform 0.2s ease;
         }
         #modalFilename span,
         #modalFilename #fileDateTime {
            display: block;
            margin: 0;
            padding: 0;
            line-height: 1;
         }
         #modalFilename #fileDateTime {
            color: gray;
            font-weight: normal;
            font-size: 0.9em;
            text-align: center;
         }
         #modalFilename span.datetime {
            margin-top: 0;
            color: #666;
            font-size: 0.9em;
         }
         #modalFilename a {
            text-decoration: none;
            vertical-align: middle;
            display: inline-block;
         }
         .leaflet-locate-control a {
            display: block;
            width: 34px;
            height: 34px;
            background: white !important;
            border-radius: 4px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4) !important;
            text-align: center;
            line-height: 34px;
            cursor: pointer;
            transition: background-color 0.2s;
         }
         .leaflet-locate-control svg {
            width: 24px;
            height: 24px;
            margin-top: 5px;
            vertical-align: top !important;
         }
         .leaflet-control-layers {
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
         }
         .leaflet-control-layers-toggle {
            background-size: 70%;
         }
         @media (max-width: 600px) {
            #modalContent {
               padding: 5px;
               max-width: calc(100vw - 20px);
               max-height: calc(100vh - 20px);
            }
            #modalContent img {
               max-width: calc(100vw - 20px);
               max-height: calc(100vh - 60px);
            }
            #modalFilename {
               font-size: 0.95em;
               padding: 0 2vw;
            }
            #modalFilename span.datetime {
               font-size: 0.85em;
               margin-top: 2px;
            }
            .leaflet-control-layers-toggle {
               width: 34px;
               height: 34px;
            }
            .leaflet-control-layers-label {
               font-size: 0.9em;
               padding: 5px 25px 5px 5px;
            }
            .leaflet-control-layers-list {
               margin: 5px;
            }
         }
         #modalContent {
            position: relative;
            background: #fff;
            padding: 5px;
            border-radius: 6px;
            box-shadow: 0 0 15px #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow: hidden;
         }
         #modalContent img {
            object-fit: contain;
            cursor: pointer;
            display: block;
            margin: 0 auto;
            max-height: 80vh;
            max-width: 90vw;
            position: relative;
         }
         #prevImage,
         #nextImage {
            position: absolute;
            top: 10%;
            bottom: 20%;
            width: 10%;
            background: rgba(0, 0, 0, 0);
            border: none;
            cursor: pointer;
            z-index: 10001;
            transition: background-color 0.2s ease;
         }
         #prevImage {
            left: 0;
         }
         #nextImage {
            right: 0;
         }
         #prevImage svg,
         #nextImage svg {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 3em;
            height: 3em;
            fill: white;
            transition:
               transform 0.2s ease,
               fill 0.2s ease;
         }
         #prevImage svg {
            left: 10%;
         }
         #nextImage svg {
            right: 10%;
         }
         #prevImage.stop svg,
         #nextImage.stop svg {
            visibility: hidden;
         }
         #prevImage,
         #nextImage {
            -webkit-tap-highlight-color: transparent;
         }
         #prevImage:hover,
         #nextImage:hover {
            background: none !important;
            box-shadow: none !important;
            outline: none !important;
            border: none !important;
         }
         .gallery-button,
         .locate-button,
         .qr-button {
            width: 36px !important;
            height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: #ffffff !important;
            border: 1px solid rgba(0, 0, 0, 0.3) !important;
            border-radius: 4px !important;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.4) !important;
            cursor: pointer !important;
            margin-bottom: 8px !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            transition: background-color 0.2s;
         }
         .gallery-button:hover,
         .locate-button:hover,
         .qr-button:hover {
            background-color: #f4f4f4 !important;
         }
         .gallery-button svg,
         .locate-icon,
         .qr-button svg {
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            min-height: 28px !important;
            display: block !important;
         }
         .qr-modal-overlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
         }
         .qr-modal-content {
            background: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
         }
         .qr-code {
            margin-bottom: 20px;
         }
         .qr-url {
            font-size: 14px;
            color: blue;
            text-decoration: underline;
         }
      </style>
      <link rel="icon" href="data:," />
      <link
         rel="stylesheet"
         href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      />
      <link
         rel="stylesheet"
         href="https://cdnjs.cloudflare.com/ajax/libs/leaflet-locatecontrol/0.84.2/L.Control.Locate.css"
      />
   </head>
   <body>
		<div id="map"></div>
		
		<div id="modalOverlay">
		  <div id="modalContent">
		    <button id="prevImage" style="position: absolute; left: 10px;">
		      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
		        <path d="M20 4l-12 12 12 12z"></path>
		      </svg>
		    </button>
		
		    <img id="modalImage" src="" alt="Large View" />
		
		    <button id="nextImage" style="position: absolute; right: 10px;">
		      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
		        <path d="M12 4l12 12-12 12z"></path>
		      </svg>
		    </button>
		
		    <div id="modalFilename">
		      <div style="display: flex; align-items: center; gap: 8px;">
		        <span id="fileName"></span>
		      </div>
		      <div id="fileDateTime" style="color: gray; font-weight: normal; font-size: 0.9em; margin-top: 4px; text-align: center;">
		      </div>
		    </div>
		  </div>
		</div>
		
		<div id="qrModalOverlay" class="qr-modal-overlay">
		  <div id="qrModalContent" class="qr-modal-content">
		    <div id="qrCode" class="qr-code"></div>
		    <a id="qrUrl" href="#" target="_blank" class="qr-url"></a>
		  </div>
		</div>

      <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
      <script src="https://unpkg.com/leaflet-gpx@1.7.0/gpx.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-locatecontrol/0.84.2/L.Control.Locate.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/piexifjs"></script>
      <script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
      <script>


    class PhotoTrackViewer {
        #crosshairSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 34.89"><path d="M2.5 8.11c-1.26 1.34-2.05 3.3-2.02 5.14 0.06 3.84 1.8 5.3 4.57 10.59 1 2.33 2.04 4.79 3.03 8.87 0.14 0.6 0.27 1.16 0.33 1.21 0.06 0.05 0.2-0.51 0.33-1.11 0.99-4.08 2.03-6.54 3.03-8.87 2.77-5.29 4.5-6.75 4.57-10.59 0.03-1.84-0.76-3.8-2.02-5.14-1.44-1.53-3.61-2.67-5.92-2.72-2.31-0.05-4.48 1.09-5.92 2.62z" fill="#ff4646" stroke="#d73534" stroke-width="1"/><circle cx="10" cy="14.87" r="3.04" fill="#590000"/></svg>`;
        #greenCrosshairSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 34.89"><path d="M2.5 8.11c-1.26 1.34-2.05 3.3-2.02 5.14 0.06 3.84 1.8 5.3 4.57 10.59 1 2.33 2.04 4.79 3.03 8.87 0.14 0.6 0.27 1.16 0.33 1.21 0.06 0.05 0.2-0.51 0.33-1.11 0.99-4.08 2.03-6.54 3.03-8.87 2.77-5.29 4.5-6.75 4.57-10.59 0.03-1.84-0.76-3.8-2.02-5.14-1.44-1.53-3.61-2.67-5.92-2.72-2.31-0.05-4.48 1.09-5.92 2.62z" fill="#46ff46" stroke="#34d735" stroke-width="1"/><circle cx="10" cy="14.87" r="3.04" fill="#005900"/></svg>`;
        #trackColors = ['#FF0000', '#0000FF', '#00FF00', '#FFA500', '#800080', '#00FFFF', '#FF00FF', '#32CD32', '#FF69B4', '#FFFF00'];

        constructor(images, gpxFiles) {
            this.images = images;
            this.gpxFiles = gpxFiles;

            this.currentImageIndex = 0;
            this.imageDataMap = new Map();
            this.objectUrlsToCleanup = new Set();
            this.currentLocationMarker = null;


            this.modalOverlay = document.getElementById('modalOverlay');
            this.modalImage = document.getElementById('modalImage');
            this.modalFilename = document.getElementById('modalFilename');
            this.qrModalOverlay = document.getElementById('qrModalOverlay');
            this.qrCodeContainer = document.getElementById('qrCode');
            this.qrUrl = document.getElementById('qrUrl');
            
            this.map = L.map('map', { preferCanvas: true });
        }

        init() {
            this.#initMapLayers();
            this.#initControls();
            this.#initEventListeners();
            this.#updateModalSize();

            if (this.images.length === 0) {
                this.map.setView([0, 0], 2);
                this.#loadGpxTracks();
            } else {
                this.#processAllImages().then(() => {
                    this.#fitMapToMarkers();
                    setTimeout(() => this.#loadGpxTracks(), 50);
                });
            }
        }
        
        #initMapLayers() {
            const osmLayer = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: "&copy; OpenStreetMap contributors"
            });
            const satelliteLayer = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
                maxZoom: 20,
                attribution: "Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community"
            });
            const baseLayers = { "OpenStreetMap": osmLayer, "Satellite": satelliteLayer };
            osmLayer.addTo(this.map);
            L.control.layers(baseLayers).addTo(this.map);
        }

        #initControls() {
            this.#addGalleryButton();
            this.#addQrButton();
            this.#addLocateButton();
        }

        #initEventListeners() {
            document.getElementById('prevImage').addEventListener('click', () => this.#navigateImage(-1));
            document.getElementById('nextImage').addEventListener('click', () => this.#navigateImage(1));
            this.modalImage.onclick = () => this.#closeModal();
            this.modalOverlay.onclick = (e) => {
                if (e.target === this.modalOverlay) this.#closeModal();
            };
            this.qrModalOverlay.onclick = (e) => {
                if (e.target === this.qrModalOverlay) this.#closeQRModal();
            };
            this.qrCodeContainer.addEventListener('click', () => this.#closeQRModal());
            window.addEventListener('resize', () => this.#updateModalSize());

            window.addEventListener('beforeunload', () => {
                this.objectUrlsToCleanup.forEach(url => URL.revokeObjectURL(url));
            });
        }

        #navigateImage(direction) {
            const newIndex = this.currentImageIndex + direction;
            if (newIndex >= 0 && newIndex < this.images.length) {
                this.currentImageIndex = newIndex;
                const fileName = this.images[this.currentImageIndex];
                this.modalImage.src = `img/${fileName}`;
                this.modalImage.onload = () => {
                    this.#updateModalContent();
                    this.#updateButtonSymbols();
                };
            }
        }
        
        #addGalleryButton() {
            const galleryButton = L.control({ position: 'topright' });
            galleryButton.onAdd = () => {
                const div = L.DomUtil.create('div', 'gallery-button-container');
                div.innerHTML = `<a href="gallery.php?path=img" target="_blank" class="gallery-button"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></a>`;
                return div;
            };
            galleryButton.addTo(this.map);
        }

        #addQrButton() {
            const qrControl = L.control({ position: "topright" });
            qrControl.onAdd = () => {
                const div = L.DomUtil.create("div", "qr-button");
                div.title = "Show QR-Code";
                div.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zm-3 0h2v2h-2v-2zm3 3h3v2h-3v-2zm-3 0h2v2h-2v-2zm3 3h3v2h-3v-2zm-3 0h2v2h-2v-2zM13 13h2v2h-2v-2zm0 3h2v2h-2v-2z"/></svg>`;
                L.DomEvent.disableClickPropagation(div);
                div.onclick = () => this.#openQRModal();
                return div;
            };
            qrControl.addTo(this.map);
        }

        #addLocateButton() {
            const locateControl = L.control({ position: "topright" });
            locateControl.onAdd = () => {
                const div = L.DomUtil.create("div", "locate-button");
                div.innerHTML = `<div style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100%;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg></div>`;
                div.addEventListener("click", () => {
                    if (navigator.geolocation) {
                        const loadingMessage = L.control({ position: "topleft" });
                        loadingMessage.onAdd = () => {
                            const loadingDiv = L.DomUtil.create("div", "loading-message");
                            loadingDiv.innerHTML = `<div style="background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-family: sans-serif;"><span>Locating ...</span></div>`;
                            return loadingDiv;
                        };
                        loadingMessage.addTo(this.map);
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const { latitude: userLat, longitude: userLng } = position.coords;
                                this.map.setView([userLat, userLng], 15);
                                if (this.currentLocationMarker) {
                                    this.map.removeLayer(this.currentLocationMarker);
                                }
                                this.currentLocationMarker = L.circle([userLat, userLng], { color: "blue", fillColor: "#30f", fillOpacity: 0.5, radius: 10 }).addTo(this.map);
                                this.map.removeControl(loadingMessage);
                            },
                            (error) => {
                                if (loadingMessage) this.map.removeControl(loadingMessage);
                                const errorMessage = L.control({ position: "topleft" });
                                errorMessage.onAdd = function () {
                                    const errorDiv = L.DomUtil.create("div", "error-message");
                                    errorDiv.innerHTML = `<div style="background: #ffdddd; color: #d8000c; padding: 10px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-family: sans-serif;"><span>Locating error ...</span></div>`;
                                    return errorDiv;
                                };
                                errorMessage.addTo(this.map);
                                setTimeout(() => this.map.removeControl(errorMessage), 3000);
                            },
                            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                        );
                    }
                });
                return div;
            };
            locateControl.addTo(this.map);
        }

        #addThumbnailToMap(lat, lon, imageUrl, fileName) {
            const thumbnailSize = 100;
            const thumbnailIcon = L.divIcon({
                className: 'thumbnail-marker',
                html: `<img src="${imageUrl}" alt="Thumbnail" style="cursor: pointer;" />`,
                iconSize: [thumbnailSize, thumbnailSize],
                iconAnchor: [thumbnailSize / 2, thumbnailSize / 2]
            });
            const marker = L.marker([lat, lon], { icon: thumbnailIcon }).addTo(this.map);
            marker.on('click', () => this.#openModal(fileName));
        }

        #openModal(fileName) {
            this.currentImageIndex = this.images.indexOf(fileName);
            if (this.currentImageIndex === -1) return;
            
            const fileNameToLoad = this.images[this.currentImageIndex];
            this.modalImage.src = `img/${decodeURIComponent(fileNameToLoad)}`;
            this.modalImage.onload = () => {
                this.#updateModalContent();
                this.#updateButtonSymbols();
                this.modalOverlay.style.display = 'flex';
            };
        }

        #closeModal() {
            this.modalOverlay.style.display = 'none';
            this.modalImage.src = '';
            this.modalFilename.textContent = '';
            this.modalImage.onload = null;
            this.modalImage.onerror = null;
            modalImage.onerror = null;
        }

        #openQRModal() {
            let currentUrl = window.location.href;
            if (currentUrl.endsWith('/')) {
                currentUrl = currentUrl.slice(0, -1);
            }
            this.qrCodeContainer.innerHTML = '';
            new QRCode(this.qrCodeContainer, { text: currentUrl, width: 400, height: 400 });
            this.qrUrl.href = currentUrl;
            this.qrUrl.textContent = currentUrl;
            this.qrModalOverlay.style.display = 'flex';
        }

        #closeQRModal() {
            this.qrModalOverlay.style.display = 'none';
        }
        
        async #processAllImages() {
            const validImages = this.images.filter(image => image);
            const batchSize = 10;
            for (let i = 0; i < validImages.length; i += batchSize) {
                const batch = validImages.slice(i, i + batchSize);
                const results = await Promise.allSettled(
                    batch.map(image => new Promise((resolve, reject) => this.#fetchAndProcessImage(image, resolve, reject)))
                );
                results.forEach(result => {
                    if (result.status === 'fulfilled' && result.value) {
                        const { fileName, gps, dateTime } = result.value;
                        this.imageDataMap.set(fileName, { gps, dateTime });
                    }
                });
                await new Promise(resolve => setTimeout(resolve, 5));
            }
        }
        
        #fetchAndProcessImage(fileName, resolve, reject) {
            fetch(`min/${decodeURIComponent(fileName)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.blob();
                })
                .then(blob => {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        try {
                            let exifData = null;
                            try { exifData = piexif.load(event.target.result); } catch (e) {
                                resolve({ fileName, gps: null, dateTime: null });
                                return;
                            }
                            const gps = this.#processGpsData(exifData);
                            const dateTime = exifData?.Exif?.[piexif.ExifIFD.DateTimeOriginal] || null;
                            if (gps) {
                                const blobUrl = URL.createObjectURL(blob);
                                this.objectUrlsToCleanup.add(blobUrl);
                                this.#addThumbnailToMap(gps.lat, gps.lon, blobUrl, fileName);
                            }
                            resolve({ fileName, gps, dateTime });
                        } catch (error) {
                            resolve({ fileName, gps: null, dateTime: null });
                        }
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(error => reject(`Error loading image ${fileName}: ${error.message}`));
        }
        
        #processGpsData(exifData) {
            if (!exifData?.GPS) return null;
            const gpsData = exifData.GPS;
            const lat = this.#convertGpsToDecimal(gpsData[piexif.GPSIFD.GPSLatitude], gpsData[piexif.GPSIFD.GPSLatitudeRef]);
            const lon = this.#convertGpsToDecimal(gpsData[piexif.GPSIFD.GPSLongitude], gpsData[piexif.GPSIFD.GPSLongitudeRef]);
            return lat && lon ? { lat, lon } : null;
        }

        #convertGpsToDecimal(gpsData, ref) {
            if (!gpsData || gpsData.length < 3) return null;
            try {
                const degrees = gpsData[0][0] / (gpsData[0][1] || 1);
                const minutes = gpsData[1][0] / (gpsData[1][1] || 1);
                const seconds = gpsData[2][0] / (gpsData[2][1] || 1);
                const decimal = degrees + minutes / 60 + seconds / 3600;
                return (ref === "S" || ref === "W") ? -decimal : decimal;
            } catch {
                return null;
            }
        }

        #fitMapToMarkers() {
            const allMarkers = [];
            this.map.eachLayer(layer => {
                if (layer instanceof L.Marker) allMarkers.push(layer.getLatLng());
            });
            if (allMarkers.length > 0) {
                const bounds = L.latLngBounds(allMarkers);
                this.map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        #loadGpxTracks() {
            this.gpxFiles.forEach((gpxFile, index) => {
                const color = this.#trackColors[index % this.#trackColors.length];
                new L.GPX('gpx/' + decodeURIComponent(gpxFile), {
                    async: true,
                    marker_options: { startIconUrl: false, endIconUrl: false, shadowUrl: '' },
                    polyline_options: { color, weight: 5, opacity: 0.8, lineCap: 'round' }
                }).addTo(this.map);
            });
        }
        
        #updateModalContent() {
            if (this.currentImageIndex < 0 || this.currentImageIndex >= this.images.length) return;
            const fileName = this.images[this.currentImageIndex];
            const filenameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
            const minData = this.imageDataMap.get(fileName);
            let lat = "", lon = "", minDateTime = "";
            if (minData) {
                if (minData.gps) {
                    ({ lat, lon } = this.#shortenCoordinates(minData.gps.lat, minData.gps.lon));
                }
                minDateTime = this.#formatDateTime(minData.dateTime);
            }
            const { googleMapsLink, secondLink } = this.#getMapLink(lat, lon);
            const crosshairLinkGoogle = lat ? `<a href="${googleMapsLink}" target="_blank" rel="noopener" title="Open in Google Maps" style="text-decoration:none;vertical-align:middle;">${this.#crosshairSVG}</a>` : '';
            const crosshairLinkSecond = lat ? `<a href="${secondLink}" target="_blank" rel="noopener" title="Open Link" style="text-decoration:none;vertical-align:middle;">${this.#greenCrosshairSVG}</a>` : '';
            this.modalFilename.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span>${filenameWithoutExt}</span>
                    ${crosshairLinkGoogle}
                    ${crosshairLinkSecond}
                </div>
                <div id="fileDateTime">${minDateTime || ""}</div>`;
        }

        #updateButtonSymbols() {
            const prevButton = document.getElementById('prevImage');
            const nextButton = document.getElementById('nextImage');
            const isFirst = this.currentImageIndex === 0;
            const isLast = this.currentImageIndex === this.images.length - 1;

            prevButton.classList.toggle('stop', isFirst);
            prevButton.innerHTML = isFirst
                ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect x="8" y="8" width="16" height="16" fill="gray"></rect></svg>`
                : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M20 4l-12 12 12 12z"></path></svg>`;

            nextButton.classList.toggle('stop', isLast);
            nextButton.innerHTML = isLast
                ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect x="8" y="8" width="16" height="16" fill="gray"></rect></svg>`
                : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M12 4l12 12-12 12z"></path></svg>`;
        }
        
        #updateModalSize() {
            const vh = window.innerHeight;
            const vw = window.innerWidth;
            document.getElementById('modalContent').style.maxHeight = `${vh - 40}px`;
            document.getElementById('modalContent').style.maxWidth = `${vw - 40}px`;
            this.modalImage.style.maxHeight = `${vh - 100}px`;
            this.modalImage.style.maxWidth = `${vw - 40}px`;
        }
        
        #formatDateTime(dateTime) {
            if (!dateTime) return null;
            try {
                const [datePart, timePart] = dateTime.split(" ");
                const formattedDate = datePart.replace(/:/g, "-");
                return `${formattedDate} ${timePart.substring(0, 5)}`;
            } catch {
                return dateTime;
            }
        }

        #getMapLink(lat, lon) {
            const ua = navigator.userAgent || navigator.vendor || window.opera;
            let googleMapsLink = `https://maps.google.com/?q=${lat},${lon}`;
            let secondLink = `https://brouter.de/brouter-web/#map=18/${lat}/${lon}/osm-mapnik-german_style&lonlats=${lon},${lat}&pois=${lon},${lat},Position`;

            if (/iPad|iPhone|iPod/.test(ua)) {
                secondLink = `comgooglemaps://?center=${lat},${lon}&zoom=18`;
            } else if (/android/i.test(ua)) {
                secondLink = `geo:${lat},${lon}`;
            }
            return { googleMapsLink, secondLink };
        }

        #shortenCoordinates(lat, lon, precision = 6) {
            return { lat: parseFloat(lat.toFixed(precision)), lon: parseFloat(lon.toFixed(precision)) };
        }
    }

    const images = <?php echo json_encode(getImageList()); ?>;
    const gpxFiles = <?php echo json_encode($gpxFiles); ?>;


    document.addEventListener('DOMContentLoaded', () => {
        const app = new PhotoTrackViewer(images, gpxFiles);
        app.init();
    });
         
      </script>
   </body>
</html>
