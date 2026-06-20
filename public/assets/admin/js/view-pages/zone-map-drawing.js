"use strict";

window.ZoneMapDrawing = {
    init: function (options) {
        return new ZoneMapDrawingInstance(options);
    },
};

function ZoneMapDrawingInstance(options) {
    this.map = null;
    this.lastPolygon = null;
    this.isDrawing = false;
    this.drawingPath = [];
    this.vertexMarkers = [];
    this.previewPolyline = null;
    this.mapClickListener = null;
    this.mapRightClickListener = null;
    this.searchMarkers = [];

    this.mapElementId = options.mapElementId || "map-canvas";
    this.coordinatesSelector = options.coordinatesSelector || "#coordinates";
    this.searchInputId = options.searchInputId || "pac-input";
    this.defaultCenter = options.defaultCenter;
    this.initialPaths = options.initialPaths || null;
    this.initialPolygonOptions = options.initialPolygonOptions || {};
    this.onCoordinatesChange = options.onCoordinatesChange || null;

    this._initMap();
    this._createToolbar();
    this._initSearch();

    if (this.initialPaths && this.initialPaths.length) {
        this._drawFinalPolygon(this.initialPaths, true);
        this._fitBoundsToPaths(this.initialPaths);
        this.setPanMode();
    } else if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            this.map.setCenter({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            });
        });
        this.setDrawMode();
    } else {
        this.setDrawMode();
    }
}

ZoneMapDrawingInstance.prototype._initMap = function () {
    this.map = new google.maps.Map(document.getElementById(this.mapElementId), {
        zoom: 13,
        center: this.defaultCenter,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
    });
};

ZoneMapDrawingInstance.prototype._createToolbar = function () {
    const mapElement = document.getElementById(this.mapElementId);
    const mapWarper = mapElement ? mapElement.closest(".map-warper") : null;
    const searchInput = document.getElementById(this.searchInputId);

    if (!mapWarper || !searchInput) {
        return;
    }

    mapWarper.style.position = "relative";

    let toolbar = mapWarper.querySelector(".zone-map-toolbar");
    if (!toolbar) {
        toolbar = document.createElement("div");
        toolbar.className = "zone-map-toolbar";
        mapWarper.insertBefore(toolbar, mapElement);
    }

    toolbar.style.cssText =
        "position:absolute;top:10px;left:50%;transform:translateX(-50%);z-index:10;" +
        "display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:4px;" +
        "max-width:calc(100% - 24px);background:#fff;border-radius:6px;" +
        "box-shadow:0 2px 6px rgba(0,0,0,0.25);padding:3px 5px;pointer-events:auto;";

    searchInput.classList.add("zone-map-search");
    toolbar.appendChild(searchInput);
    searchInput.style.cssText =
        "display:block;position:static;height:26px;min-height:26px;width:200px;min-width:140px;" +
        "max-width:100%;margin:0;padding:2px 8px;font-size:12px;line-height:1.2;" +
        "border:1px solid #d0d7de;border-radius:4px;flex:1 1 200px;background:#fff;box-shadow:none;";

    const controls = document.createElement("div");
    controls.className = "zone-map-drawing-controls";

    this.panButton = this._makeControlButton(
        "Hand tool",
        "tio-hand-draw",
        () => this.setPanMode()
    );
    this.drawButton = this._makeControlButton(
        "Shape tool",
        "tio-free-transform",
        () => this.setDrawMode()
    );
    const resetButton = this._makeControlButton(
        "Reset polygon",
        "tio-clear",
        () => this.clearPolygon()
    );

    controls.appendChild(this.panButton);
    controls.appendChild(this.drawButton);
    controls.appendChild(resetButton);
    toolbar.appendChild(controls);

    this.finishButton = document.createElement("button");
    this.finishButton.type = "button";
    this.finishButton.className = "zone-map-finish-btn";
    this.finishButton.textContent = "Finish";
    this.finishButton.addEventListener("click", () => this.finishDrawing());
    toolbar.appendChild(this.finishButton);

    this.toolbar = toolbar;
};

ZoneMapDrawingInstance.prototype._makeControlButton = function (title, iconClass, onClick) {
    const button = document.createElement("button");
    button.type = "button";
    button.title = title;
    button.className = "zone-map-tool-btn";
    button.innerHTML = '<i class="' + iconClass + '"></i>';
    button.style.cssText =
        "width:26px;height:26px;min-width:26px;border:0;border-radius:4px;padding:0;" +
        "background:#fff;color:#333;cursor:pointer;display:inline-flex;" +
        "align-items:center;justify-content:center;font-size:14px;flex-shrink:0;";
    button.addEventListener("click", onClick);
    return button;
};

ZoneMapDrawingInstance.prototype._setActiveTool = function (activeButton) {
    [this.panButton, this.drawButton].forEach((button) => {
        button.classList.remove("active");
    });
    if (activeButton) {
        activeButton.classList.add("active");
    }
};

ZoneMapDrawingInstance.prototype.setPanMode = function () {
    this.isDrawing = false;
    this._clearDrawingPreview();
    this._removeDrawingListeners();
    this.map.setOptions({ draggable: true, draggableCursor: null, draggingCursor: null });
    this.finishButton.style.display = "none";
    this._setActiveTool(this.panButton);
};

ZoneMapDrawingInstance.prototype.setDrawMode = function () {
    this.isDrawing = true;
    this._clearDrawingPreview();
    this.drawingPath = [];
    this.map.setOptions({ draggable: true, draggableCursor: "crosshair" });
    this.finishButton.style.display = "none";
    this._setActiveTool(this.drawButton);
    this._removeDrawingListeners();

    this.mapClickListener = this.map.addListener("click", (event) => {
        this._addVertex(event.latLng);
    });
    this.mapRightClickListener = this.map.addListener("rightclick", (event) => {
        event.stop();
        if (this.drawingPath.length > 0) {
            this._removeLastVertex();
        }
    });
};

ZoneMapDrawingInstance.prototype._addVertex = function (latLng) {
    this.drawingPath.push(latLng);

    const marker = new google.maps.Marker({
        map: this.map,
        position: latLng,
        clickable: false,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 5,
            fillColor: "#050df2",
            fillOpacity: 1,
            strokeColor: "#ffffff",
            strokeWeight: 1,
        },
    });
    this.vertexMarkers.push(marker);
    this._updatePreview();
    this.finishButton.style.display = this.drawingPath.length >= 3 ? "inline-block" : "none";
};

ZoneMapDrawingInstance.prototype._removeLastVertex = function () {
    this.drawingPath.pop();
    const marker = this.vertexMarkers.pop();
    if (marker) {
        marker.setMap(null);
    }
    this._updatePreview();
    this.finishButton.style.display = this.drawingPath.length >= 3 ? "inline-block" : "none";
};

ZoneMapDrawingInstance.prototype._updatePreview = function () {
    if (this.previewPolyline) {
        this.previewPolyline.setMap(null);
        this.previewPolyline = null;
    }

    if (this.drawingPath.length < 2) {
        return;
    }

    this.previewPolyline = new google.maps.Polyline({
        path: this.drawingPath,
        strokeColor: "#050df2",
        strokeOpacity: 0.8,
        strokeWeight: 2,
        map: this.map,
    });
};

ZoneMapDrawingInstance.prototype.finishDrawing = function () {
    if (this.drawingPath.length < 3) {
        if (typeof toastr !== "undefined") {
            toastr.warning("Minimum 3 points are required.");
        }
        return;
    }

    const path = this.drawingPath.map((latLng) => ({
        lat: latLng.lat(),
        lng: latLng.lng(),
    }));

    this._clearDrawingPreview();
    this._drawFinalPolygon(path, true);
    this.setPanMode();
};

ZoneMapDrawingInstance.prototype._drawFinalPolygon = function (path, editable) {
    if (this.lastPolygon) {
        this.lastPolygon.setMap(null);
    }

    const options = Object.assign(
        {
            paths: path,
            strokeColor: "#050df2",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#050df2",
            fillOpacity: 0.15,
            editable: editable !== false,
        },
        this.initialPolygonOptions
    );

    this.lastPolygon = new google.maps.Polygon(options);
    this.lastPolygon.setMap(this.map);
    this._syncCoordinatesFromPolygon(this.lastPolygon);

    if (editable !== false) {
        const pathObject = this.lastPolygon.getPath();
        ["set_at", "insert_at", "remove_at"].forEach((eventName) => {
            google.maps.event.addListener(pathObject, eventName, () => {
                this._syncCoordinatesFromPolygon(this.lastPolygon);
            });
        });
    }
};

ZoneMapDrawingInstance.prototype._syncCoordinatesFromPolygon = function (polygon) {
    const path = polygon.getPath().getArray();
    $(this.coordinatesSelector).val(path);
    if (typeof auto_grow === "function") {
        auto_grow();
    }
    if (this.onCoordinatesChange) {
        this.onCoordinatesChange(path);
    }
};

ZoneMapDrawingInstance.prototype.clearPolygon = function () {
    if (this.lastPolygon) {
        this.lastPolygon.setMap(null);
        this.lastPolygon = null;
    }
    this._clearDrawingPreview();
    $(this.coordinatesSelector).val("");
    if (typeof auto_grow === "function") {
        auto_grow();
    }
};

ZoneMapDrawingInstance.prototype._clearDrawingPreview = function () {
    this.drawingPath = [];
    this.vertexMarkers.forEach((marker) => marker.setMap(null));
    this.vertexMarkers = [];

    if (this.previewPolyline) {
        this.previewPolyline.setMap(null);
        this.previewPolyline = null;
    }
};

ZoneMapDrawingInstance.prototype._removeDrawingListeners = function () {
    if (this.mapClickListener) {
        google.maps.event.removeListener(this.mapClickListener);
        this.mapClickListener = null;
    }
    if (this.mapRightClickListener) {
        google.maps.event.removeListener(this.mapRightClickListener);
        this.mapRightClickListener = null;
    }
};

ZoneMapDrawingInstance.prototype._fitBoundsToPaths = function (paths) {
    const bounds = new google.maps.LatLngBounds();
    paths.forEach((point) => bounds.extend(point));
    this.map.fitBounds(bounds);
};

ZoneMapDrawingInstance.prototype._initSearch = function () {
    const input = document.getElementById(this.searchInputId);
    if (!input) {
        return;
    }

    input.style.display = "block";

    if (!google.maps.places) {
        console.warn("Google Places library failed to load. Enable Places API for your Maps API key.");
        return;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
        fields: ["geometry", "name", "formatted_address"],
    });
    autocomplete.bindTo("bounds", this.map);

    autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();
        if (!place.geometry || !place.geometry.location) {
            return;
        }

        this.searchMarkers.forEach((marker) => marker.setMap(null));
        this.searchMarkers = [];

        this.searchMarkers.push(
            new google.maps.Marker({
                map: this.map,
                title: place.name || place.formatted_address,
                position: place.geometry.location,
            })
        );

        if (place.geometry.viewport) {
            this.map.fitBounds(place.geometry.viewport);
        } else {
            this.map.setCenter(place.geometry.location);
            this.map.setZoom(15);
        }
    });
};
