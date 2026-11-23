'use strict';

const el = id => document.getElementById(id);
const show = (id, visible) => { el(id).hidden = !visible; };
const message = (id, value = '') => { el(id).textContent = value; };
const coords = point => [point.latitude, point.longitude];
const token = document.querySelector('meta[name="csrf-token"]').content;
let game = null;
let phase = 'home';
let map, watch, currentMarker, pinMarker, pin;
let startPending = false;

async function post(path, data) {
    const response = await fetch('/walk/' + path, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
        body: JSON.stringify(data)
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 419) throw new Error('セッションが切れました。ページを再読み込みしてください。');
        throw new Error(response.status < 500 && payload.message || '通信できませんでした。もう一度お試しください。');
    }
    return payload;
}

const gpsOptions = {enableHighAccuracy: true, timeout: 15000, maximumAge: 0};
function readFix(position) {
    const {latitude, longitude, accuracy} = position.coords;
    return {latitude, longitude, accuracy, timestamp: position.timestamp};
}
function gpsError(error) {
    return error.code === 1 ? '位置情報の利用を許可してください。デモでも遊べます。' : '現在地を取得できません。屋外で再取得してください。';
}
function getFix() {
    if (!navigator.geolocation || !window.isSecureContext) throw new Error('位置情報の取得には HTTPS または localhost が必要です。');
    return new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(
        p => resolve(readFix(p)), e => reject(new Error(gpsError(e))), gpsOptions));
}
function marker(point, style, title, letter) {
    const icon = L.divIcon({className: '', html: '<span class="pin ' + style + '">' + letter + '</span>', iconSize: [26,26], iconAnchor: [13,13]});
    return L.marker(coords(point), {icon, title, alt: title}).bindTooltip(title).addTo(map);
}
function stopWatch() {
    if (watch != null) navigator.geolocation.clearWatch(watch);
    watch = null;
}
function home() {
    stopWatch();
    if (map) map.remove();
    map = currentMarker = pinMarker = pin = game = null;
    phase = 'home';
    show('home', true); show('play', false);
    message('startMessage'); el('start').focus(); window.scrollTo(0,0);
}
function setPin(point) {
    pin = point;
    if (pinMarker) pinMarker.setLatLng(coords(point));
    else pinMarker = marker(point, 'guess', '回答ピン', '予');
    el('action').disabled = false;
    message('gameMessage');
}

async function updateLocation(point, session) {
    if (game !== session || phase === 'result') return;
    session.location = point;
    currentMarker.setLatLng(coords(point));
    try {
        const data = await post(session.round + '/distance', point);
        if (game === session && session.location === point && phase !== 'result') {
            message('distance', data.distance + ' m');
            message('gameMessage');
        }
    } catch (error) {
        if (game === session && phase !== 'result') message('gameMessage', error.message);
    }
}
function renderPhase(next) {
    phase = next;
    const answer = next === 'answer', result = next === 'result';
    show('hintBox', !result); show('resultInfo', result); show('score', result);
    show('back', answer); show('pinHere', answer);
    message('gameTitle', result ? '今回の探索結果' : answer ? 'ここが正解地点？' : 'この景色を探そう');
    message('distanceLabel', result ? '回答ピンと正解地点の距離' : '目的地までの直線距離');
    message('action', result ? 'もう一度遊ぶ' : answer ? 'ここで回答を確定' : '回答する');
    el('action').disabled = answer && !pin;
    message('instruction', result ? '「正」が正解地点、「予」があなたの回答です。' : answer ? '地図をタップしてピンを置いてください。ピンから60 m以内で回答できます。' :
        game.demo ? 'デモでは地図をタップすると、その地点まで歩いたことになります。' : '青い点が現在地です。写真と残りの距離を手がかりに歩いてみましょう。');
}
async function start(demo) {
    if (startPending) return;
    startPending = true; el('start').disabled = el('demo').disabled = true;
    message('startMessage', demo ? '写真を準備中…' : '現在地を取得中…');
    try {
        if (!window.L) throw new Error('地図を読み込めません。ページを再読み込みしてください。');
        const form = new FormData(el('setup'));
        const fix = demo ? {} : await getFix();
        const session = await post('start', {minutes: Number(form.get('minutes')), era: form.get('era'), demo, ...fix});
        message('photoCredit', session.credit);
        session.demo = demo; session.location = demo ? session.origin : fix;
        game = session; pin = null;
        message('startMessage'); message('gameMessage'); message('eraLabel', form.get('era') === 'modern' ? '現代の写真' : '昭和14年の街角 · 写真と同じ方向を眺められる歩道を探そう');
        message('mode', demo ? '渋谷デモ' : '現地探索');
        show('home', false); show('play', true); show('imageError', false);
        el('hints').replaceChildren(); el('hint').disabled = !session.hints.length; message('hint','ヒントを見る');
        el('clueImage').src = '/walk/' + session.round + '/photo';
        map = L.map('map').setView(coords(session.location),16);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        tiles.on('tileerror', () => { if (game === session) message('tileMessage','地図の一部を取得できません。通信環境を確認してください。'); });
        currentMarker = marker(session.location,'me','現在地','●');
        map.on('click', event => {
            const point = {latitude: event.latlng.lat, longitude: event.latlng.lng};
            if (phase === 'answer') setPin(point);
            if (phase === 'explore' && demo) updateLocation(point, session);
        });
        renderPhase('explore'); message('tileMessage'); message('distance', session.distance + ' m');
        if (!demo) watch = navigator.geolocation.watchPosition(p => updateLocation(readFix(p), session),
            e => { if (game === session) message('gameMessage',gpsError(e)); }, gpsOptions);
        window.scrollTo(0,0); el('gameTitle').focus();
    } catch (error) {
        if (game) home();
        message('startMessage', error.message);
    } finally {
        startPending = false; el('start').disabled = el('demo').disabled = false;
    }
}
function displayResult(result) {
    stopWatch(); renderPhase('result');
    const spot = result.spot;
    marker(spot,'answer','正解地点','正');
    L.polyline([coords(result.pin), coords(spot)], {color:'#82552c',dashArray:'6 7'}).addTo(map);
    map.fitBounds([coords(result.pin), coords(spot)], {padding:[40,40], maxZoom:18, animate:false});
    message('score', result.score + ' / 100'); message('distance', result.error + ' m');
    message('spotName',spot.name); message('description',spot.description);
    const link = document.createElement('a'); link.href = spot.source; link.textContent = '写真の出典';
    el('credit').replaceChildren(spot.credit + ' · ',link);
    if (result.comparisonSource) {
        const comparison = document.createElement('a');
        comparison.href = result.comparisonSource;
        comparison.textContent = '見比べ用の現代写真の出典';
        el('credit').append(' · ', comparison);
    }
    if (spot.licenseUrl) {
        const license = document.createElement('a'); license.href = spot.licenseUrl; license.textContent = '利用条件';
        el('credit').append(' · ',license);
    }
    el('gameTitle').focus();
}

el('setup').onsubmit = event => { event.preventDefault(); start(false); };
el('demo').onclick = () => start(true);
el('quit').onclick = home;
el('hint').onclick = () => {
    const index = el('hints').children.length;
    const li = document.createElement('li'); li.textContent = game.hints[index]; el('hints').append(li);
    if (index + 1 === game.hints.length && game.comparisonCredit) {
        const photo = document.createElement('img');
        photo.alt = '同じ交差点を歩道から眺めた現代の写真';
        photo.src = '/walk/' + game.round + '/photo?comparison=1';
        const credit = document.createElement('small');
        credit.textContent = game.comparisonCredit;
        li.append(photo, credit);
    }
    if (index + 1 >= game.hints.length) { el('hint').disabled = true; message('hint','すべてのヒントを表示しました'); }
};
el('pinHere').onclick = () => setPin(game.location);
el('back').onclick = () => {
    if (pinMarker) map.removeLayer(pinMarker);
    pin = pinMarker = null; message('gameMessage'); renderPhase('explore');
};
el('clueImage').onerror = () => show('imageError',true);
el('clueImage').onload = () => show('imageError',false);
el('retryImage').onclick = () => { el('clueImage').src = '/walk/' + game.round + '/photo?t=' + Date.now(); };
el('action').onclick = async () => {
    if (phase === 'result') return home();
    if (phase === 'explore') return renderPhase('answer');
    if (!pin) return;
    const session = game;
    const chosen = {...pin};
    phase = 'submitting'; el('action').disabled = el('pinHere').disabled = el('back').disabled = true;
    message('gameMessage','回答を確認中…');
    try {
        const recent = Date.now() - session.location.timestamp <= 15000 && session.location.accuracy <= 80;
        const fix = session.demo || recent ? session.location : await getFix();
        if (game !== session) return;
        const result = await post(session.round + '/answer', {...fix,pin:chosen});
        if (game === session) { message('gameMessage'); displayResult(result); }
    } catch (error) {
        if (game === session) { renderPhase('answer'); message('gameMessage',error.message); }
    } finally {
        el('pinHere').disabled = el('back').disabled = false;
    }
};
el('setup').addEventListener('change', () => show('eraNote', new FormData(el('setup')).get('era') === 'showa'));
