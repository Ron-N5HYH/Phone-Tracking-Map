<?php
$mqtt_ws_url = 'ws://192.168.101.3:9001';
$mqtt_topic  = 'owntracks/+/+';
$map_title   = 'OpenTracks Live Position';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($map_title) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { margin:0; padding:0; font-family: system-ui, sans-serif; }
        #map { width:100%; height:100vh; }
        .info {
            position: absolute; top: 10px; left: 10px; z-index: 1000;
            background: rgba(255,255,255,0.96); padding: 14px 18px; border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.35); font-size: 14px; max-width: 460px;
        }
        .good { color: #006400; }
        .debug { font-size: 12px; color: #444; margin-top: 8px; white-space: pre-wrap; }
    </style>
</head>
<body>

<div id="map"></div>

<div class="info">
    <strong><?= htmlspecialchars($map_title) ?></strong><br>
    <span id="status" class="good">✅ Connected • Subscribed to owntracks/+/+</span>
    <div id="coords" style="margin-top:8px; font-size:13.5px;">Waiting for first location...</div>
    <div id="debug" class="debug"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

<script>
const config = {
    wsUrl: <?= json_encode($mqtt_ws_url) ?>,
    topic: <?= json_encode($mqtt_topic) ?>
};

let map, marker, lastTst = 0;

function initMap() {
    map = L.map('map').setView([32.96146, -97.09142], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    marker = L.marker([32.96146, -97.09142]).addTo(map);
}

function updatePosition(lat, lon, tst, acc=null, batt=null, vel=null, topic='') {
    const pos = [parseFloat(lat), parseFloat(lon)];
    marker.setLatLng(pos);
    map.flyTo(pos, 17, {duration: 1.5});

    let extra = '';
    if (acc) extra += ` ±${Math.round(acc)}m`;
    if (vel && vel > 0) extra += ` • ${vel} km/h`;
    if (batt) extra += ` • Batt ${batt}%`;

    document.getElementById('coords').innerHTML = `
        📍 <strong>${lat.toFixed(6)}, ${lon.toFixed(6)}</strong>${extra}<br>
        Updated: ${new Date(tst*1000).toLocaleTimeString()}
    `;

    document.getElementById('debug').textContent = `Received on: ${topic}\nTST: ${tst} (${Math.floor(Date.now()/1000 - tst)}s ago)`;
    lastTst = tst;
}

function connectMQTT() {
    const client = mqtt.connect(config.wsUrl);

    client.on('connect', () => {
        client.subscribe(config.topic, {qos: 1});
    });

    client.on('message', (topic, payload) => {
        try {
            const msg = JSON.parse(payload.toString());
            document.getElementById('debug').textContent = `Message on: ${topic} | _type: ${msg._type || 'unknown'}`;

            if (msg._type === "location" && msg.lat && msg.lon) {
                updatePosition(msg.lat, msg.lon, msg.tst, msg.acc, msg.batt, msg.vel, topic);
            }
        } catch(e) {
            console.error("Parse error", e);
        }
    });
}

window.onload = () => {
    initMap();
    connectMQTT();
};
</script>
</body>
</html>


