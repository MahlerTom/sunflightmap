<?php
include("lib/global.php");

// allow input from URL
$autoload = false;

$date_depart=date("Y-m-d\TH:i"); // ie: 2013-12-31T15:35
//$date_depart="2013-12-31T15:35";
if (array_key_exists("date", $_GET)) {
	$date_depart=$_GET['date'];
	$autoload = true;
}

$origins = Array("JFK", "MIA", "ORD");
$origin = $origins[array_rand($origins)];
if (array_key_exists("origin", $_GET)) {
	$origin = $_GET['origin'];
}

$destinations = Array("SFO", "LAX", "SEA", "LHR", "LIS", "YVR");
$destination = $destinations[array_rand($destinations)];
if (array_key_exists("destination", $_GET)) {
	$destination = $_GET['destination'];
}

$duration = "8.0 hours";
if (array_key_exists("duration", $_GET)) {
	$duration = $_GET['duration'];
}

if(array_key_exists("autoload", $_GET)) {
	$autoload = true;
}

?>
<!DOCTYPE html> 
<html> 

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1"> 
	<title>SunFlight.net - Day and Night Flight Map</title>

	<meta name="description" content="SunFlight is an app that shows you the path of the sun for your flight.">
	<!--<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">-->
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	
	<meta property="og:title" content="SunFlight.net" /> 
	<meta property="og:description" content="SunFlight is an app that shows you the path of the sun for your flight."/> 
	
	<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/themes/excite-bike/jquery-ui.css" type="text/css" media="screen, projection" />
	<!--<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" type="text/css"/>-->
	<link rel="stylesheet" href="css/stylesheet.css" type="text/css" media="screen, projection" />
	<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />

	<!-- libraries -->
	<!--<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>-->
	<!--<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>-->
	<script type="text/javascript" src="http://maps.googleapis.com/maps/api/js?libraries=geometry&sensor=false"></script>
	<link rel="stylesheet" href="http://code.jquery.com/mobile/1.3.2/jquery.mobile-1.3.2.min.css" />
	<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
	<!--<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>-->
	<script src="http://code.jquery.com/mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>

	<!-- http://stackoverflow.com/questions/7936119/setting-maximum-width-for-content-in-jquery-mobile -->
	<style type='text/css'>
    <!--
        html { background-color: #f9f9f9; }
        @media only screen and (min-width: 600px){
            .ui-page {
                width: 600px !important;
                margin: 0 auto !important;
                position: relative !important;
                border-right: 5px #666 outset !important;
                border-left: 5px #666 outset !important;
            }
        }
    -->
    </style>

	<!-- custom code -->
	<script type="text/javascript" src="/js/daynightmaptype.js"></script>
	<script type="text/javascript" src="/js/jQueryRotate.2.2.js"></script>
	<script type="text/javascript" src="/js/richmarker-compiled.js"></script>

	<script type="text/javascript">
	
	var map;
	var flightPaths = Array();
	var markers = Array();
	var flightMarker = null;
	var sunMarker = null;
	var dn = null;
	// day night shadow
	// track if we have initialised the slider yet
	var timeslider = null;
	var firstLoad = true;

	// Update configuration to our liking
	$( document ).on( "pageinit", "#home", function( event ) {

		// create date for jquey mobile
		// ie: 2013-12-31T15:35
		var today = new Date();
		var dd = today.getDate();
		var mm = today.getMonth()+1;//January is 0!
		var yyyy = today.getFullYear();
		var hours = today.getHours();
		var minutes = today.getMinutes();
		var seconds = today.getSeconds();
		if(dd<10){dd='0'+dd}
		if(mm<10){mm='0'+mm}
		if(hours<10){hours='0'+hours}
		if(minutes<10){minutes='0'+minutes}

		var datetime=yyyy+'-'+mm+'-'+dd+'T'+hours+':'+minutes;
		$("#requestDate").val(datetime);
	});

	$(document).ready(function() {

		main = function() {
	        //google.maps.event.addDomListener(window, 'load', initializeMap);
	        <?php
	        if (array_key_exists("debug", $_GET)) { ?>$('#debug').show(); <?php
	        } ?>
	        updatePermalink();
	        
	        $('#enter-flight-code').bind('expand', expandFlightCodeContainer);
	        $('#enter-flight-code').bind('collapse', collapseFlightCodeContainer);

	        <?php
	        if ((!isChrome() && !isiPad() && !isiPhone())) { ?>$("#chrome_note").show(); <?php
	        } ?>
	        //init();
	    }

	    function initializeMap(centerMap) {
	        var myOptions = {
	            zoom: 1,
	            maxZoom: 3,
	            minZoom: 1,
	            center: centerMap,
	            mapTypeId: google.maps.MapTypeId.ROADMAP,
	            streetViewControl: false,
	            mapTypeControl: false,
	            panControl: false,
    			<?php if (isMobile()) { ?>draggable: false,<?php } ?>
    			scrollwheel: false
	        };
	        map = new google.maps.Map(document.getElementById('map_canvas'), myOptions);
	        <?php
	        if ($autoload) { ?>
	        	mapFlight(); 
	        <?php
	        } ?>
	    }

	    expandFlightCodeContainer = function() {
	    	$('#enter-flight-code-title').text("Enter Route Details");
	    	$('#map_container').hide();
	    	$('#results-panel').hide();
	    	$('#show-developer-info').hide();
	    }

	    collapseFlightCodeContainer = function() {

	    	// validate input
	        if (!validateInput()) {
	            return;
	        }

	        // update enter flights header
	   			$('#enter-flight-code-title').text("Route Map: " + getInputOrigin() + " to " + getInputDestination() + " dep " + getInputRequestDate() + ", " + getInputDuration() + " hrs");
	    }


	    clearMapRoutes = function() {
	        //alert(flightPaths.length);
	        // remove existing polys
	        for (i = 0; i < flightPaths.length; i++) {
	            flightPaths[i].setMap(null);
	        }
	        for (i = 0; i < markers.length; i++) {
	            markers[i].setMap(null);
	        }
	        // reset array
	        flightPaths = Array();
	        markers = Array();
	    }

	    trim = function(str) {
	        return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	    }

	    // ie:
	    // JQ7 = JQ
	    // A3-594 = A3
	    // A3594 = A3
	    getInputCarrierCode = function() {
	    	var input = $('#carrierCodeAndServiceNumber').val();

	    	// first, check if there is a non alpha numeric
	    	if (input.match(/\W/) != null) {
	    		var parts = input.split(/\W/);
	    		return trim(parts[0]);
	    	}

	    	// ICAO code - ie: QFA
	    	if (input.match(/^[A-Za-z]{3}\d/) != null) {
	    		return trim(input.substr(0,3));
	    	}

	    	// IATA code - ie: QF
	        return trim(input.substr(0,2));
	    }

	    // ie:
	    // JQ7 = 7
	    // A3-594 = 594
	    // A3594 = 594
	    getInputServiceNumber = function() {

	    	var input = $('#carrierCodeAndServiceNumber').val();

	    	// first, check if there is a non alpha numeric
	    	if (input.match(/\W/) != null) {
	    		var parts = input.split(/\W/);
	    		return trim(parts[1]);
	    	}

	    	// ICAO code - ie: QFA
	    	if (input.match(/^[A-Za-z]{3}\d/) != null) {
	    		return trim(input.substr(3));
	    	}

	    	// IATA code - ie: QF
	        return trim(input.substr(2));
	    }

	    getInputRequestDate = function() {
	        return trim($('#requestDate').val());
	    }

	    getInputOrigin = function() {
	        return trim($('#origin').val());
	    }

	    getInputDestination = function() {
	        return trim($('#destination').val());
	    }

	    getInputDuration = function() {
	        return trim($('#duration').val().replace(/[^0-9.]/g, "")); // remove non digits
	    }

	    validateInput = function() {
	        if (getInputOrigin() == "") {
	            alert("Please enter an origin airport (ie: SYD)");
	            return false;
	        }
	        if (getInputDestination() == "") {
	            alert("Please enter a destination airport (ie: LHR)");
	            return false;
	        }
	        if (getInputRequestDate() == "") {
	            alert("Please enter a date and time of travel");
	            return false;
	        }
	        if (getInputDuration() == "") {
	            alert("Please enter duration of flight in hours (ie: 12)");
	            return false;
	        }
	        if (getInputDuration() < 0) {
	            alert("Duration can't be negative");
	            return false;
	        }
	        return true;
	        // valid!
	    }

	    mapFlight = function() {

	        // validate input
	        if (!validateInput()) {
	            return;
	        }

	        // make permalink
	        updatePermalink();

	        // clear previous map routes
	        clearMapRoutes();

	        // show loading page
	        $.mobile.showPageLoadingMsg(); //$('#loading-page').show();
	        $('#results-panel').hide();

	        // lookup flight data
	        $.getJSON("/ajax/ajax-flight-route-manual.php?callback=?",
	        {
	         		origin: getInputOrigin(), // SYD
	         		destination: getInputDestination(), // DXB
	         		departure_datetime: getInputRequestDate(), // 2014-03-10 04:30 pm
	         		duration: getInputDuration() * 60 // 600 
	            //"2011-10-14"
	        },
	        function(data) {

	        	if (data.error != null) {
	        		$.mobile.hidePageLoadingMsg();
	        		//$('#loading-page').hide();
	        	 	$('#enter-flight-code').trigger('expand');
	        		alert(data.error);
	        	} else {
	        		$("#cached_result").val(data.cached);
	        		clearAdvancedText();
	        		initFlightRoutes(data.flight_segments);
	        	}
	        });
	    }

	    drawFlightRoute = function(data) {

            // get lat lon
            var fromLatLng = new google.maps.LatLng(data.from_lat, data.from_lon);
            var toLatLng = new google.maps.LatLng(data.to_lat, data.to_lon);

            // get date
            var depart_date = Date.parse(data.depart_time);
            //alert(depart_date.format("UTC:h:MM:ss TT Z"));

            // draw path of flight
            var flightPath = new google.maps.Polyline({
                path: [fromLatLng, toLatLng],
                strokeColor: "#FF0080",
                strokeOpacity: 1.0,
                strokeWeight: 2,
                geodesic: true,
                clickable: false
            });
            flightPaths.push(flightPath);
            flightPath.setMap(map);

	    }


	    drawFlightData = function(data) {

            var content_html = '<li data-role="list-divider">' + data.from_city + ' (' + data.from_airport + ') to ' + data.to_city + ' (' + data.to_airport + ')</li>';

            // sfcalc stats
            content_html += '<li><p style="margin-top: 2px;"><strong>';
			content_html += '<span style="color: red;">Sun Left = ' + showPercent(data.flight_stats.percent_left) + '%</span> &middot; ';
			content_html += '<span style="color: green;">Right = ' + showPercent(data.flight_stats.percent_right) + '%</span> &middot; ';
			content_html += '<span style="color: blue;">Night = ' + showPercent(data.flight_stats.percent_night) + '%</strong></p>';
			
			// first sunrise and sunset (if any)
			if (data.flight_stats.mins_to_first_sunrise < data.flight_stats.mins_to_first_sunset) {
				// sunrise is before sunset
				if (data.flight_stats.mins_to_first_sunrise > 0) {
					content_html += '<p><strong>Flight time to sunrise on ' + data.flight_stats.sunrise_left_right + ': ' + formatMinutes(data.flight_stats.mins_to_first_sunrise) + '</strong></p>';
				}
				if (data.flight_stats.mins_to_first_sunset > 0) {
					content_html += '<p><strong>Flight time to sunset on ' + data.flight_stats.sunset_left_right + ': ' + formatMinutes(data.flight_stats.mins_to_first_sunset) + '</strong></p>';
				}
			} else {
				// sunset is before sunrise
				if (data.flight_stats.mins_to_first_sunset > 0) {
					content_html += '<p><strong>Flight time to sunset on ' + data.flight_stats.sunset_left_right + ': ' + formatMinutes(data.flight_stats.mins_to_first_sunset) + '</strong></p>';
				}
				if (data.flight_stats.mins_to_first_sunrise > 0) {
					content_html += '<p><strong>Flight time to sunrise on ' + data.flight_stats.sunrise_left_right + ': ' + formatMinutes(data.flight_stats.mins_to_first_sunrise) + '</strong></p>';
				}
			}

			content_html += '</li>';


			// other flight stats
            content_html += '<li>';
			content_html += '<p><strong>Depart ' + data.from_airport + ' at ' + data.depart_time.replace("T", " ") + '</strong></p>';
			//content_html += '<p><strong>Arrive ' + data.to_airport + ' at ' + data.arrival_time.replace("T", " ") + '</strong></p>';
			content_html += '<p>Flight time: ' + formatMinutes(data.elapsed_time) + '</p>';
			var miles_to_km = 0.621371192;
			content_html += '<p>Distance: ' + addCommas(Math.round(data.distance_km * miles_to_km)) + ' miles, ' + addCommas(data.distance_km) + 'km </p>';
			content_html += '</li>'

            return content_html;
	    }


	    function showPercent(num) {
	    	if (num > 100) { num = 100; }
	    	return num;
	    }

        function addCommas(nStr)
		{
		  nStr += '';
		  x = nStr.split('.');
		  x1 = x[0];
		  x2 = x.length > 1 ? '.' + x[1] : '';
		  var rgx = /(\d+)(\d{3})/;
		  while (rgx.test(x1)) {
		    x1 = x1.replace(rgx, '$1' + ',' + '$2');
		  }
		  return x1 + x2;
		}


	    formatMinutes = function(minutes) {
	    	var hours = Math.floor(minutes / 60);
	    	if (hours > 0) {
	    		var text = hours + "hrs " + (minutes % 60) + " mins";
	    	} else {
	    		var text = (minutes % 60) + " mins";
	    	}
	    	return text;
	    }

	    resetResults = function() {

	    	// show map
	    	$('#map_container').show();
	    	
	    	$.mobile.hidePageLoadingMsg(); // $('#loading-page').hide();
	    	$('#enter-flight-code').trigger('collapse');
	    	$('#results-panel').html("");
	    }

	    drawFlightEndPoints = function(toLatLngFlag) {

	    	var circle = {
    			path: google.maps.SymbolPath.CIRCLE,
    			scale: 3.0,
    			fillColor: "#F00",
    			strokeColor: "#eee",
    			stokeWeight: 0.1
  			};

            // anchor of image
            //var toLatLngFlag = new google.maps.LatLng(data.to_lat, data.to_lon);
            var toMarker = new google.maps.Marker({
                position: toLatLngFlag,
                map: map,
                title: 'Destination',
                icon: circle
                //'/images/flag.png'
        	});
            markers.push(toMarker);
	    }

	    initTimeSlider = function(flightdata) {

	    	var first_flight = flightdata[0];
	    	var last_flight = flightdata[flightdata.length - 1];

	    	// calculate total flight time
	    	// use elapsed_time for each flight plus the time until the next departure
	    	var total_minutes = 0;
	    	for (var i = 0; i < flightdata.length; i++) {
				// calculate flight duration including layover time for next segment
	    		if (i < flightdata.length - 1) {
	    			var this_flight_arrival_time = new Date(Date.parse(flightdata[i].arrival_time));
	    			var next_flight_start_time = new Date(Date.parse(flightdata[i+1].depart_time));
	    			var flight_time_diff = Math.abs(next_flight_start_time.getTime() - this_flight_arrival_time.getTime());
	    			var flight_time_including_stopover = flightdata[i].elapsed_time + Math.ceil(flight_time_diff / 1000 / 60);
	    		} else {
	    			var flight_time_including_stopover = flightdata[i].elapsed_time;
	    		}

	    		total_minutes += flight_time_including_stopover; // add flight time included stop over
	    	}


			// record flight segment index
			var flight_segment_by_minute = []; // track which flight segment a given minute is in
			var flight_segment_start_time = []; // track the start time (of the total journey) of a flight

	    	for (var i = 0; i < flightdata.length; i++) {

	    		// calculate flight duration including layover time for next segment
	    		if (i < flightdata.length - 1) {
	    			var this_flight_arrival_time = new Date(Date.parse(flightdata[i].arrival_time));
	    			var next_flight_start_time = new Date(Date.parse(flightdata[i+1].depart_time));
	    			var flight_time_diff = Math.abs(next_flight_start_time.getTime() - this_flight_arrival_time.getTime());
	    			var flight_time_including_stopover = flightdata[i].elapsed_time + Math.ceil(flight_time_diff / 1000 / 60);
	    			//alert(flight_time_including_stopover);
	    		} else {
	    			var flight_time_including_stopover = flightdata[i].elapsed_time;
	    		}

	    		// for each minute of flight time, record the segment index (i)
	    		var flight_segment_by_minute_length = flight_segment_by_minute.length;
	    		//alert(flight_segment_by_minute_length + flight_time_including_stopover);
	    		for (var j = flight_segment_by_minute_length; j <= flight_segment_by_minute_length + flight_time_including_stopover; j++) {
	    			flight_segment_by_minute[j] = i;
	    			flight_segment_start_time[i] = j;
	    		}
	    	}

			if (timeslider != null) {
                timeslider = $("#slider").slider("destroy");
            }

	    	$("#slider_holder").empty();
            $("#slider_holder").append('<input type="range" name="slider-time" id="slider" value="0" min="0" max="100"/>');

            // init jquery mobile slider
            $("#slider_holder").show();
            $("#slider_holder input").attr('value', 0);
            $("#slider_holder input").attr('max', total_minutes);
			$("#slider_holder").trigger("create");


			$("#slider").bind("change", function(event, ui) {

  				clearTimeout(this.id);

                this.id = setTimeout(function() {

                	var slider_value = parseInt($("#slider_holder input").val());

	                //console.log(first_flight.depart_time_utc);
	                mapSunPosition(flightPaths, map, new Date(Date.parse(first_flight.depart_time_utc)), total_minutes, slider_value);
	                
	                // map path of the sun
	                // work out which flight segment we are in using flight_segment_by_minute index
	                var flight_segment = flight_segment_by_minute[slider_value];
	                var current_flight = flightdata[flight_segment];
	                $("#flight_segment").val(flight_segment + 1);
	                var relative_ui_value = slider_value;
	                if (flight_segment > 0) {
	                	relative_ui_value -= flight_segment_start_time[flight_segment-1]; // offset with previous flight
	                }
	                var minute_of_segment = relative_ui_value;


	                var flight_points = current_flight["flight_points"];

	                $("#minute_of_segment").val(relative_ui_value);

	                if (minute_of_segment < current_flight.elapsed_time) {
						var flight_point = flight_points[minute_of_segment];
	                	$("#sfcalc_sun_side").val(flight_point["sun_side"]);
	                	$("#sfcalc_sun_alt").val(flight_point["sun_alt"]);
	                	$("#sfcalc_tod").val(flight_point["tod"]);
	                	$("#sfcalc_sun_east_west").val(flight_point["sun_east_west"]);
	                	$("#sfcalc_azimuth_from_north").val(flight_point["azimuth_from_north"]);
	                	$("#sfcalc_bearing_from_north").val(flight_point["bearing_from_north"]);

	                } else {
	                	$("#sfcalc_sun_side").val("stopover");
	                	$("#sfcalc_tod").val("stopover");
	                	$("#sfcalc_sun_east_west").val("stopover");
	                	$("#sfcalc_azimuth_from_north").val("stopover");
	                	$("#sfcalc_bearing_from_north").val("stopover");
	                }

			        if (minute_of_segment < current_flight.elapsed_time) {
			        	current_bearing = flight_point["bearing_from_north"];
			        }
	                mapFlightPosition(flightPaths, map, current_flight.from_lat, current_flight.from_lon, current_flight.to_lat, current_flight.to_lon, current_flight.elapsed_time, relative_ui_value, current_bearing);
	                
	                // map path of the sun
	                mapDayNightShadow(map, new Date(Date.parse(first_flight.depart_time_utc)), slider_value);
	                $("#minutes_travelled").val(slider_value);
	                updateSliderTime(slider_value, total_minutes);  

				}, 10); // end set timeout
            }); // end change event

			// update slider to begin with
            mapSunPosition(flightPaths, map, new Date(Date.parse(first_flight.depart_time_utc)), total_minutes, 0);
            // map path of the sun
            mapFlightPosition(flightPaths, map, first_flight.from_lat, first_flight.from_lon, first_flight.to_lat, first_flight.to_lon, first_flight.elapsed_time, 0, first_flight.flight_points[0]["bearing_from_north"]);
            // map path of the sun
            mapDayNightShadow(map, new Date(Date.parse(flightdata[0].depart_time_utc)), 0);
            $("#minutes_travelled").val(0);
            updateSliderTime(0, total_minutes);

	    }

	    clearAdvancedText = function() {

	    	$("#minutes_travelled").val("");
	    	$("#slider_time").val("");
	    	$("#flight_segment").val("");
	    	$("#segment_days_of_operation").val("");
	    	$("#minute_of_segment").val("");
	    	$("#sfcalc_sun_side").val("");
        	$("#sfcalc_tod").val("");
        	$("#sfcalc_alt").val("");
        	$("#sfcalc_sun_east_west").val("");
        	$("#sfcalc_azimuth_from_north").val("");
        	$("#sfcalc_bearing_from_north").val("");
        	$("#days_of_operation").val("");
	    }

	    initFlightRoutes = function(flightdata) {

            // get back jsonp
            // flightmap({"from_airport": "MEL","from_city": "Melbourne","from_lat": -37.673333,"from_lon": 144.843333,"to_airport": "SIN","to_city": "Singapore","to_lat": 1.350189,"to_lon": 103.994433,"depart_time": "2011-10-16T12:00:00","elapsed_time": 470})
			resetResults();

			// check flight data for errors	        
	       	for(var i = 0; i < flightdata.length; i++) {

	       		// check for errors
	        	if (flightdata[i].error != "") {
	        		alert("Error processing flight route data: " + data.error);
	        		return;
	        	}

	        	var flight_points = flightdata[i]["flight_points"];
	          if (flight_points == null) {
	             alert("Oops, unable to retrieve the sun position data from the server. Please try again later.");
							 return;
						}

	        }

	        // calculate route bounds to center map
			var route_bounds = new google.maps.LatLngBounds();
	       	for(var i = 0; i < flightdata.length; i++) {
				// increase map bounds
				route_bounds.extend(new google.maps.LatLng(10, flightdata[i].from_lon));
				route_bounds.extend(new google.maps.LatLng(10, flightdata[i].to_lon));
	        }

	       	// init map with center bounds of route
			if (firstLoad) {

				<?php if (isMobile()) { ?>
					// init map with center at starting point of plane for mobile
					initializeMap(new google.maps.LatLng(10, flightdata[0].from_lon));
				<?php } else { ?>
					// init map with center at middle of route bounds for non mobile
					initializeMap(route_bounds.getCenter());
				<?php } ?>

				firstLoad = false;
			}

			// draw flight routes
	        for(var i = 0; i < flightdata.length; i++) {
	        	drawFlightRoute(flightdata[i]);
	        }

	        // draw flight route start and end points
	       	drawFlightEndPoints(new google.maps.LatLng(flightdata[0].from_lat, flightdata[0].from_lon));
			for(var i = 0; i < flightdata.length; i++) {
	        	drawFlightEndPoints(new google.maps.LatLng(flightdata[i].to_lat, flightdata[i].to_lon));
	        }

	        // draw data
	        var results_html = "";
	       	for(var i = 0; i < flightdata.length; i++) {
	       		results_html += drawFlightData(flightdata[i]);
	       	}

	        // init results list
	        // draw flight routes
	        $("#results-panel").append('<ul id="results-list" data-role="listview" data-theme="d" data-divider-theme="d">' + results_html + "</ul>");
	        //$("#results-panel").append(results_html);
	        //$("#results-panel").append('</ul>');
           	$("#results-list").listview();      

	        // show slider
	        $('#slider-container').show();
	       	$('#results-panel').fadeIn();
	       	$('#show-developer-info').show();

	    	initTimeSlider(flightdata);

	    	// need to set resize the map otherwise we get missing tiles
			// http://stackoverflow.com/questions/10489264/jquery-mobile-and-google-maps-not-rendering-correctly
			setTimeout(function() {
				map.setZoom(1);
    			google.maps.event.trigger(map,'resize');

    			<?php if (isMobile()) { ?>
    			map.setCenter(new google.maps.LatLng(10, flightdata[0].from_lon)); // set to take off point
    			<?php } else { ?>
    			map.setCenter(route_bounds.getCenter()); // set to middle of flight route bounds
    			<?php } // end if ?>
			}, 500);

	    }

	    mapDayNightShadow = function(map, UTCTime, minutesOffset) {
	        //alert(maptime);
	        if (dn == null) {

	            dn = new DayNightMapType(UTCTime, minutesOffset);
	            map.overlayMapTypes.insertAt(0, dn);
	            dn.setMap(map);
	            //dn.setAutoRefresh(10);
	            dn.setShowLights(1);
	        }
	        else {
	            dn.calcCurrentTime(UTCTime, minutesOffset);
	            dn.redoTiles();
	        }
	    }

	    mapSunPosition = function(flightPaths, map, start_time_at_gmt, duration_minutes, minutes_travelled) {

	        // Sun is directly overhead LatLng(0, 0) at 12:00:00 midday
	        // 1440 minutes / 1 minute = 0.25 degrees
	        // Assuming maximum trip duration of 24 hours / single leg
	        // Calculate sun's starting longitude from the start time at gmt
	        //console.log(start_time_at_gmt);
	        //console.log(new Date(start_time_at_gmt).getTimezoneOffset());
	        local_offset = new Date(start_time_at_gmt).getTimezoneOffset();
	        minutes_gmt = local_offset + (start_time_at_gmt.getHours() * 60) + start_time_at_gmt.getMinutes();
	        //console.log(minutes_gmt);
	        from_deg = 180 - minutes_gmt * 0.25;

	        duration_deg = duration_minutes * 0.25 * (minutes_travelled / duration_minutes);
	        to_deg = from_deg - duration_deg;

			var dayofyear= (start_time_at_gmt - new Date(start_time_at_gmt.getFullYear(),0,1)) / 86400000;
			var sunlat = -23.44*Math.sin(((dayofyear + 10 + 91.25)*Math.PI)/(365/2));

			// Starting longitude is positive
			var toLatLng = new google.maps.LatLng(sunlat, to_deg);

	        // draw sun marker
	        if (sunMarker != null) {
	            sunMarker.setMap(null);
	        }

	        var sunimage = new google.maps.MarkerImage('images/sun.png',
	        new google.maps.Size(32, 32),
	        // marker dimensions
	        new google.maps.Point(0, 0),
	        // origin of image
	        new google.maps.Point(16, 16));
	        // anchor of image
	        sunMarker = new google.maps.Marker({
	            position: toLatLng,
	            map: map,
	            title: 'Sun Position: ' + to_deg,
	            icon: sunimage
	        });
	        markers.push(sunMarker);

	    }

	    mapFlightPosition = function(flightPaths, map, startLat, startLon, endLat, endLon, duration_minutes, minutes_travelled, bearing) {

	        // draw flight marker
	        if (flightMarker != null) {
	            flightMarker.setMap(null);
	        }

	        if (minutes_travelled > duration_minutes) {
	        	minutes_travelled = duration_minutes;
	        }

	        percentage_travelled = minutes_travelled / duration_minutes;

	        var fromLatLng = new google.maps.LatLng(startLat, startLon);
	        var toLatLng = new google.maps.LatLng(endLat, endLon);

	        try {
	            var flightpos = google.maps.geometry.spherical.interpolate(fromLatLng, toLatLng, percentage_travelled);
	        }
	        catch(error) {
	            // ignore it
	        }


	        // var planeimage = new google.maps.MarkerImage('images/airplane.svg', null, null, null, new google.maps.Size(32, 32));

	        flightMarker = new google.maps.Marker({
	            position: flightpos,
	            map: map,
	            title: 'Flight position: ' + to_deg,
	            icon: {
			        scale: 1.2,
			        path: "m16.194347,3.509549c0.7269,0 1.333155,0.605579 1.333155,1.372868l0,8.077136l11.306938,6.34025l0,2.826685l-11.306938,-3.270845l0,5.895784l2.665304,1.978716l0,2.342138l-3.99892,-1.333424l-3.997725,1.3325l0,-2.342411l2.665575,-1.978714l0,-5.895763l-11.308268,3.271421l0,-2.826664l11.308268,-6.340271l0,-8.077136c0,-0.767288 0.604597,-1.372868 1.332093,-1.372868l0.000519,0.000597z",
			        origin: new google.maps.Point(0, 0),
			        anchor: new google.maps.Point(16, 16),
			        strokeWeight: 0.5,
			        fillOpacity: 1,
			        fillColor: "#FF0",
			        //strokeColor: "#303030",
			        rotation: bearing
			    }
	        	});
	        markers.push(flightMarker);

	        // set map centre with flight on mobile devices
	        <?php if (isMobile()) { ?>
	        map.setCenter(flightMarker.getPosition());
	        <?php } ?>
	        
	    }

	    function updateSliderTime(t, max)
	    {
	        //slider_text = t + " mins";
	        slider_text = formatMinutes(t);
	        if (t == 0) {
	            slider_text = "Use the slider to view your flight path";
	        }
	        else if (t == max) {
	            slider_text = "Landed!";
	        }

	        $("#map_container label").text(slider_text);
	        $('#slider_time').val(slider_text);
	    }

	    function updatePermalink()
	    {
	        $('#permalink').attr("href", "http://" + window.location.hostname + "/?origin=" + getInputOrigin() + "&destination=" + getInputDestination() + "&date=" + getInputRequestDate() + "&duration=" + getInputDuration());
	    }


	    <?php if ($autoload) { ?>
	    resetResults();
	    mapFlight();
	    <?php } ?>

	    <?php if (isMobile()) { ?>
	    $("#map_canvas").css("height", "200px");
	    <?php } ?>

	    // let's do it!
	    main();

	});
	</script>
		
	
</head>
<body>

	<!-- Start of first page: #one -->
<div data-role="page" id="home">

	<div data-role="header">
		<h1>Someone please <a href='https://www.tnooz.com/article/sunflight-choose-your-airline-seat-based-on-what-side-the-sun-will-be/'>adopt me</a> :)</h1>
		<div data-role="navbar">
			<ul>
				<li><a href="#home" class="ui-btn-active">Flight Map</a></li>
				<li><a href="#faq">FAQ</a></li>
			</ul>
		</div><!-- /navbar -->

	</div><!-- /header -->

	<div data-role="content" id="home">	
		
			<div id="chrome_note" style="display: none;"><p style="font-size: smaller;">(Please note: This app works best in Google Chrome)</p></div>

			<div data-role="collapsible" data-collapsed="false" id="enter-flight-code">
	   			<h3><span id="enter-flight-code-title"><?php if($autoload) { ?>Loading...<?php } else { ?>Enter Route Details <?php } ?></span></h3>

				<input id="origin" value="<?php print($origin);?>" size="5">
				<input id="destination" value="<?php print($destination);?>" size="5">
				<input type="datetime-local" data-clear-btn="false" name="requestDate" id="requestDate" value=""> <!--<?php print($date_depart); ?>-->
				<input id="duration" value="<?php print($duration);?>" size="5">

				<button onClick="mapFlight();" data-theme="e">Show Flight Map</button>
				<div id="random_flight">
					<p>Or, show me a <a rel=external href="/?autoload">random flight</a></p>
				</div>
			</div>


			<div id="map_container" style="width: 100%; display: none;">

				<div id="map_canvas" style="width: 100%;">Loading map...</div>

				<label for="slider-0"></label>
				<div id="slider_holder" style="width: 100%; display: none;">
   					<input type="range" name="slider-time" id="slider" value="0" min="0" max="100"/>
   				</div>

				<p><a id="permalink" style="color: blue;" rel=external href="#">Link to this map</a></p>
    		</div>

    		<div id="results-panel" style="padding-top: 8px;"></div>

			<div data-role="collapsible" id="show-developer-info" style="display: none; padding-top: 16px;">
	   			<h3>Advanced</h3>

	   			<div data-role="fieldcontain">
					<label for="minutes_travelled">Minutes Traveled:</label>
					<input type="text" name="minutes_travelled" id="minutes_travelled" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="slider_time">Hours Traveled:</label>
					<input type="text" name="slider_time" id="slider_time" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="flight_segment">Flight Segment:</label>
					<input type="text" name="flight_segment" id="flight_segment" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="minute_of_segment">Minute of Segment:</label>
					<input type="text" name="minute_of_segment" id="minute_of_segment" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_sun_side">Sun position:</label>
					<input type="text" name="sfcalc_sun_side" id="sfcalc_sun_side" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_sun_alt">Sun alt:</label>
					<input type="text" name="sfcalc_sun_alt" id="sfcalc_sun_alt" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_tod">Time of day:</label>
					<input type="text" name="sfcalc_tod" id="sfcalc_tod" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_sun_east_west">Sun East/West:</label>
					<input type="text" name="sfcalc_sun_east_west" id="sfcalc_sun_east_west" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_azimuth_from_north">Solar Azimuth (from North):</label>
					<input type="text" name="sfcalc_azimuth_from_north" id="sfcalc_azimuth_from_north" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="sfcalc_azimuth_from_north">Flight bearing (from North):</label>
					<input type="text" name="sfcalc_bearing_from_north" id="sfcalc_bearing_from_north" value="" />
				</div>

				<div data-role="fieldcontain">
					<label for="cached_result">OAG Cached result:</label>
					<input type="text" name="cached_result" id="cached_result" value="" />
				</div>

			</div>

	</div><!-- /content -->
	
	<div data-role="footer" data-theme="d">
		<h4>Flight hacker? <a href="https://github.com/aussieian/sunflightmap">Fork on Github</a></h4>
	</div><!-- /footer -->
</div><!-- /home page -->



<!-- Start of second page: #faq -->
<div data-role="page" id="faq" data-theme="a">

	<div data-role="header">
		<h1>Frequently Asked Questions</h1>
	</div><!-- /header -->

	<div data-role="content" data-theme="a">	
		<h2>FAQ</h2>

		<p><strong>Q: What is SunFlight?</strong>
		<br>A: SunFlight is an app that shows you the path of the sun for your flight. 
		You can use it for whatever purpose you want, however most people use this app to help plan where to sit on their flight, based on the location of the sun.<br>
		This app is open source, so feel free have a look at <a href="https://github.com/aussieian/sunflightmap">GitHub</a>.
		</p>

		<p><strong>Q: Are you still maintaining SunFlight?</strong>
		<br>A: I am no longer maintaining SunFlight. However since I've made the source code open source, you can always improve the app yourself. If you make any noteable enhancements and would like me to update the app, please fork the code and send me a diff to look at. If you would like to take over maintaining sunflight, then also please get in contact with me.<br>

		<p><strong>Q: When will the sun will rise and set on my flight?</strong>
		<br>SunFlight also tells you when the sunrise and sunset will be on your flight, so you can plan your nap and sleep times. The results summary will indicate when the sunset or sunrise will occur for each leg of your journey.
		</p>

		<!--<p><strong>Q: Tell me more about how SunFlight works</strong>
		<br>A: SunFlight calculates the solar altitude and azimuth for every minute of the flight (based on a geodesic path of the flight), and then calculates which side of the plane the sun will be on based on the current bearing of the flight.
		</p>

		<p><strong>Q: At what point is the sunset/sunrise calculated?</strong>
		<br>A: Sunset and sunrise is determined when the sun's altitude reaches 6 degrees from the horizon from the ground. 
		</p>-->

		<p><strong>Q: Does SunFlight calculate the exact flight path?</strong>
		<br>A: SunFlight uses the geodesic (shortest path) between two points, which in most cases will simulate the approximate flight path. 
		</p>

		<p><strong>Q: How can I contact the creator of this site?</strong>
		<br>A: Contact me at <a href="mailto:ian@travelmassive.com">ian@travelmassive.com</a>
		</p>

		<p><strong>Q: Does it work on iPad / iPhone?</strong>
		<br>A: Yes it should work with the latest iOS version for both iPad and iPhones.
		</p>

		<p><a href="#home" data-rel="back" data-role="button" data-inline="true" data-icon="back">Back to home page</a></p>	
		
	</div><!-- /content -->
	
	<div data-role="footer">
		<h4>SunFlight.net</h4>
	</div><!-- /footer -->
</div><!-- /page donate -->


	<!-- google anlaytics -->
	<script type="text/javascript">

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-26345499-1']);
	  _gaq.push(['_trackPageview']);

	  (function() {
	    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();

	</script>
	<!-- analytics -->
	
</body>
</html>

