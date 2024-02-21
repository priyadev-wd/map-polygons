<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Map</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Google Maps API Script -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAQFPt8TsmOX2hl9b2XCziGXYbdDBLS3e4&language=en&libraries=drawing,places,geometry&callback=initMap" async defer></script>
    <style>
        #map {
            height: 400px;
            width: 100%;
        }

        #customDiv {
            position: absolute;
            bottom: 20px !important;
            left: 20px !important;
            z-index: 1000;
            top:85% !important;
        }
    </style>

</head>

<body>
    <!-- Custom div with buttons -->
    <div id="customDiv">
        <button class="btn btn-primary" id="draw_field" onclick="enablePolygonDrawing()">Draw Field</button>
        <button class="btn btn-secondary" id="draw_unused_area" onclick="enableChildDrawing()">Draw Unused Area</button>
        <button class="btn btn-warning" id="reset_map" onclick="resetDrawing()">Reset Map</button>
        <button class="btn btn-success" id="save_field" onclick="saveFields()">Save Field</button>
    </div>

    <!-- Map container -->
    <input type="text" class="form-control" id="parentPolygon"><br/>
    <input type="text" class="form-control" id="childPolygon">
    <div id="map"></div>

    
   
    <script>
        var parentPolygon = [];
        var childPolygon = [];

        var drawingManager;
        var map;
        var contextMenuPolygon = null;
        var contextMenu = null;
        var previousValidState;
        function initMap() {
            // Create a map centered at the user's location
            navigator.geolocation.getCurrentPosition(function (position) {
                var userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                map = new google.maps.Map(document.getElementById('map'), {
                    center: userLocation,
                    zoom: 14
                });

                // Custom div with buttons
                var customDiv = document.getElementById('customDiv');
                map.controls[google.maps.ControlPosition.TOP_LEFT].push(customDiv);

                // Load predefined polygons
                loadPredefinedPolygons(map);
                // Center map to the nearest polygon
                centerMapToNearestPolygon(map, userLocation);
            }, function () {
                // Handle geolocation error
                alert('Error: The Geolocation service failed.');
            });
        }

        function enablePolygonDrawing() {

            if(parentPolygon.length==0)
            {
                map.setZoom(20);
                drawingManager = new google.maps.drawing.DrawingManager({
                    drawingMode: google.maps.drawing.OverlayType.POLYGON,
                    drawingControl: false,
                    polygonOptions: {
                        editable: true,
                        draggable: false,
                        fillColor: 'transparent', // Set the fillColor to transparent
                        strokeColor: '#000000', // Set the strokeColor to red
                        strokeWeight: 2,
                        
                    }
                });

                drawingManager.setMap(map);
                google.maps.event.addListener(drawingManager, 'polygoncomplete', function (polygon) {
                    parentPolygon.push(polygon);
                    addPolygonEditListeners(polygon);
                    console.log("Polygon saved:", polygon.getPath().getArray());
                    drawingManager.setOptions({ drawingMode: null });
                });
            }else{
                console.log("There can only be single parent polygon");
                return false;
            }
           
        }

        function enableChildDrawing() {
            if (parentPolygon.length != 0) {
                map.setZoom(20);
                drawingManager = new google.maps.drawing.DrawingManager({
                    drawingMode: google.maps.drawing.OverlayType.POLYGON,
                    drawingControl: false,
                    polygonOptions: {
                        editable: true,
                        draggable: false,
                        fillColor: '#ff0000',
                        strokeColor: '#ff0000',
                        strokeWeight: 2
                    }
                });

                drawingManager.setMap(map);
                google.maps.event.addListener(drawingManager, 'polygoncomplete', function (polygon) {
                        if (isPolygonInsideParent(polygon) && !isPolygonsOverlap(polygon, childPolygon)) {
                            childPolygon.push(polygon);
                            console.log("Child Polygon length: " + childPolygon.length);
                            attachContextMenu(polygon);
                            addPolygonEditListeners(polygon);
                            previousValidState = polygon.getPath().getArray(); // Save the initial valid state
                        } else {
                            alert("Please draw the polygon inside the parent polygon without overlapping.");
                            polygon.setMap(null);
                        }
                        drawingManager.setOptions({ drawingMode: null });
                    });
            } else {
                return false;
            }
        }

        
        function addPolygonEditListeners(polygon) {
            var path = polygon.getPath();

            google.maps.event.addListener(path, 'set_at', function (index) {
                var coordinates = [];
                path.forEach(function(latLng) {
                    coordinates.push({ lat: latLng.lat(), lng: latLng.lng() });
                });

                // Send coordinates to PHP via AJAX
                $.ajax({
                    url: 'check_overlap.php',
                    type: 'POST',
                    data: { coordinates: coordinates },
                    success: function(response) {
                        // Handle the response from PHP
                        if (response.overlaps) {
                            // Handle overlapping case
                            alert('Overlap detected!');
                        } else {
                            // No overlap
                            console.log('No overlap detected');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle errors
                        console.error(error);
                    }
                });
            });
        }

        function doLineSegmentsIntersect(line1Start, line1End, line2Start, line2End) {
            function ccw(A, B, C) {
                return (C.lat() - A.lat()) * (B.lng() - A.lng()) > (B.lat() - A.lat()) * (C.lng() - A.lng());
            }

            return ccw(line1Start, line2Start, line2End) !== ccw(line1End, line2Start, line2End) &&
                ccw(line1Start, line1End, line2Start) !== ccw(line1Start, line1End, line2End);
        }


        function isPolygonsOverlap(newPolygon, existingPolygons) {
            for (var i = 0; i < existingPolygons.length; i++) {
                var existingPolygon = existingPolygons[i];
                var existingPath = existingPolygon.getPath().getArray();
                var newPath = newPolygon.getPath().getArray();

                for (var j = 0; j < existingPath.length; j++) {
                    var next = (j + 1) % existingPath.length;

                    for (var k = 0; k < newPath.length; k++) {
                        var nextNew = (k + 1) % newPath.length;

                        if (doLineSegmentsIntersect(existingPath[j], existingPath[next], newPath[k], newPath[nextNew])) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }


        function doEdgesIntersect(point1, point2, polygonPath) {
            for (var i = 0; i < polygonPath.length; i++) {
                var next = (i + 1) % polygonPath.length;
                if (doLineSegmentsIntersect(point1, point2, polygonPath[i], polygonPath[next])) {
                    return true;
                }
            }
            return false;
        }


        function isPolygonInsideParent(polygon) {
            if (parentPolygon.length < 1) {
                console.error("No parent polygon found.");
                return false;
            }

            var parentPolygons = parentPolygon[0];
            var parentPolygonPath = parentPolygons.getPath().getArray();
            var polygonPath = polygon.getPath().getArray();

            // Check if every point of the polygon is inside the parent polygon
            for (var i = 0; i < polygonPath.length; i++) {
                if (!google.maps.geometry.poly.containsLocation(polygonPath[i], parentPolygons)) {
                    return false;
                }
            }

            return true;
        }

        
    </script>
     <script src="shape_file.js"></script>
</body>

</html>
