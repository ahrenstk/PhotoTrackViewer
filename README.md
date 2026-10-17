# PhotoTrackViewer

**PhotoTrackViewer** is a lightweight, single-page web application built with PHP and Leaflet.js that transforms your travel photos and GPX tracks into an interactive map-based tour viewer. You can use `index.php` with local copys of external CSS and JavaScript or `index-cdn.php` for loading CSS and JavaScript from CDNs.

**Demo**: **[https://ahrens.tk/Bourtange](https://ahrens.tk/Bourtange)** (more images than the download version)

**Notice**: This application is for private use only. For public or commercial usage, please ensure compliance with the terms of service and privacy policies of third-party map providers (OpenStreetMap and Esri).

 

##Features

* **Interactive Map Viewer:** Switch seamlessly between OpenStreetMap and Esri satellite layers.
* **GPX Track Rendering:** Automatically loads and styles multiple GPX routes from the local directory.
* **EXIF Photo Integration:** Asynchronously extracts GPS coordinates and timestamps from image EXIF data using `piexifjs` to place thumbnail markers directly onto the map.
* **Rich Image Modal:** Click any thumbnail to view the full-size photo, browse through the gallery with built-in navigation, and view capture dates and precise coordinate links (Google Maps, BRouter, or native device mapping apps).
* **Geolocation Support:** Center the map on your current location with a single click.
* **Mobile QR Sharing:** Instantly generate and display a QR code for the current tour URL to easily open it on mobile devices.
* **Zero Dependencies / Simple Setup:** Requires no heavy build tools or databases—just drop it into a directory with your photos and GPX files.

## Project Structure

```text
├── index.php         # Main application file (Backend PHP logic + Frontend UI/JS)
|── upload.php        # Upload software to upload images and gpx-files
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

1. Place `index.php` in your web server directory.
2. Create three subfolders: `img/`, `min/`, and `gpx/` or use upload.php
3. Add your full-size photos to `img/` and their corresponding resized thumbnail versions to `min/`.
4. Add your `.gpx` track files to the `gpx/` folder.
5. Open the directory in your browser to explore your interactive tour!

## License

This project is licensed under the **MIT License**. You can find the licensing details in the `LICENSE` file.
