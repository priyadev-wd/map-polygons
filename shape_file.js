
function centerMapToNearestPolygon(map, userLocation) {
    // Find the nearest polygon and adjust the map
    var nearestPolygon = findNearestPolygon(map, userLocation);

    if (nearestPolygon) {
        var bounds = new google.maps.LatLngBounds();
        nearestPolygon.getPath().forEach(function (point) {
            bounds.extend(point);
        });

        map.fitBounds(bounds);
    }
}

function findNearestPolygon(userLocation) {
    // Iterate through polygons to find the nearest one
    var nearestPolygon = null;
    var nearestDistance = Number.MAX_VALUE;

    parentPolygon.forEach(function (polygon) {
        var polygonBounds = new google.maps.LatLngBounds();
        polygon.getPath().forEach(function (point) {
            polygonBounds.extend(point);
        });

        var distance = google.maps.geometry.spherical.computeDistanceBetween(
            userLocation,
            polygonBounds.getCenter()
        );

        if (distance < nearestDistance) {
            nearestDistance = distance;
            nearestPolygon = polygon;
        }
    });

    return nearestPolygon;
}

function loadPredefinedPolygons(map) {
    // Define polygon coordinates
    var polygonCoords = [
        { lat: 30.699333, lng: 76.697628 },
        { lat: 30.697755, lng: 76.698937 },
        { lat: 30.700043, lng: 76.702617 },
        { lat: 30.701584, lng: 76.701265 },
        { lat: 30.699333, lng: 76.697628 }
    ];

    // Construct the polygon
    var polygon = new google.maps.Polygon({
        paths: polygonCoords,
        strokeColor: '#FF0000',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: '#FF0000',
        fillOpacity: 0.35
    });

    // Set the polygon on the map
    polygon.setMap(map);
}


function saveFields() { 
    var allParentCoordinates = [];
    parentPolygon.forEach(function (polygon) {
        var polygonCoordinates = getPolygonCoordinates(polygon);
        allParentCoordinates.push(polygonCoordinates);
    });
    console.log(JSON.stringify(allParentCoordinates));
    $("#parentPolygon").val(JSON.stringify(allParentCoordinates));

    var allChildCoordinates = [];
    childPolygon.forEach(function (polygon) {
        var polygonCoordinates = getPolygonCoordinates(polygon);
        allChildCoordinates.push(polygonCoordinates);
    });
    console.log(JSON.stringify(allChildCoordinates));
    $("#childPolygon").val(JSON.stringify(allChildCoordinates));
}

function getPolygonCoordinates(polygon) {
    var polygonCoordinates = [];
    var polygonPath = polygon.getPath();
    
    polygonPath.forEach(function (latLng) {
        polygonCoordinates.push({
            lat: latLng.lat(),
            lng: latLng.lng()
        });
    });
    return polygonCoordinates;
}

function createContextMenu(event, polygon) {
    if (contextMenu) {
        contextMenu.close();
    }
    contextMenu = new google.maps.InfoWindow();
    var deleteOption = document.createElement('div');
    deleteOption.innerHTML = 'Delete Polygon';

    google.maps.event.addDomListener(deleteOption, 'click', function () {
        deletePolygon(polygon);
        contextMenu.close();
    });

    contextMenu.setContent(deleteOption);
    contextMenu.setPosition(event.latLng);
    contextMenu.open(map);
}
function attachContextMenu(polygon) {
    google.maps.event.addListener(polygon, 'rightclick', function (event) {
        contextMenuPolygon = polygon;
        createContextMenu(event, polygon);
    });
}
function deletePolygon(polygon) {
    polygon.setMap(null);
    var index = childPolygon.indexOf(polygon);
    if (index > -1) {
        childPolygon.splice(index, 1);
    }
}
function resetDrawing() { 
    // Remove any existing drawn polygons
    console.log("length "+parentPolygon.length);
    console.log("length "+childPolygon.length);
    for (var i = 0; i < parentPolygon.length; i++) 
    {
        parentPolygon[i].setMap(null);
    }
    for (var i = 0; i < childPolygon.length; i++) 
    {
        childPolygon[i].setMap(null);
    }

    parentPolygon = [];
    childPolygon = [];
    drawingManager.setDrawingMode(null);

    $("#parentPolygon").val("");
    $("#childPolygon").val("");

}
