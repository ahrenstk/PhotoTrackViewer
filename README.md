# PhotoTrackViewer

**PhotoTrackViewer** is a lightweight, single-page Leaflet web application built with PHP and JavaScript that transforms your travel photos and GPX tracks into an interactive map-based tour viewer. You can use `index.php` with local copys of external CSS and JavaScript or `index-cdn.php` for loading CSS and JavaScript from CDNs. For multiple tours, copy the project into different folders.

**Demo**: **[https://ahrens.tk/Bourtange](https://ahrens.tk/Bourtange)** (more images than in the download version)

**Another Demo**: **[https://ahrens.tk/Radtour/2026/Fastenmarsch](https://ahrens.tk/Radtour/2026/Fastenmarsch/)**


**Notice**: This application is for private use only. For public or commercial usage, please ensure compliance with the terms of service and privacy policies of third-party map providers (OpenStreetMap and Esri).

 

## Features

* **Interactive Map Viewer**: Switch seamlessly between OpenStreetMap and Esri satellite layers.
* **GPX Track Rendering**: Automatically loads and styles multiple GPX routes in multiple colours from the local directory `gpx`.
* **EXIF Photo Integration**: Extracts GPS coordinates and timestamps from image EXIF data in `min`using `piexifjs` to place thumbnail markers directly onto the map.
* **Rich Image Modal**: Click any thumbnail from `min`to view the full-size photo `img`, browse through the gallery with built-in navigation, and view capture dates and precise coordinate links (Google Maps, BRouter, or native device mapping apps).
* **Geolocation Support**: Center the map on your current location with a single click.
* **Mobile QR Sharing**: Instantly generate and display a QR code for the current tour URL to easily open it on mobile devices.
* **Zero Dependencies / Simple Setup**: Requires no heavy build tools or databases—just drop it into a directory with your photos and GPX files.
* **Nice image names**: Rename your images before upload or after upload in `min` and `img` for better names

## Project Structure

```text
├── index.php         # Main application file (Backend PHP logic + Frontend UI/JS)
|── upload.php        # Upload software to upload images and gpx-files (use optional)
├── index-cdn.php     # Main application file (Backend PHP logic + Frontend UI/JS) (version with CDNs)
|── upload-cdn.php    # Upload software to upload images and gpx-files (version with CDNs)
├── img/              # Full-size original images (set resize in uplaod.php or upload-cdn.php or change on your own)
├── min/              # Thumbnail images (used for map markers)
└── gpx/              # GPX track files (optional)

```

## Requirements

* A web server with **PHP** support (PHP 7.4 or higher recommended).
* Modern web browser with JavaScript enabled.

## Getting Started

1. Unzip PhotoTrackViewer.zip in a folder
2. Open your website to test with the pictures and gpx-track from my demo (pictures and track from [Fortress Bourtange](https://www.bourtange.co.uk)) 
4. Delete content or folders `min`, `img` and `gpx`
5. Upload images and gpx-tracks e.g. with `upload.php` or manually
8. Open your website again

## License

This project is licensed under the **MIT License**. You can find the licensing details in the `LICENSE` file.
