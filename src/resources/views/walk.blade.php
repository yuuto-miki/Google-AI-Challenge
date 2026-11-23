<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#164d3c">
<title>WALK QUEST | 渋谷の散歩探索ゲーム</title>
<link rel="stylesheet" href="/walk/leaflet/leaflet.css"><link rel="stylesheet" href="/walk/style.css">
<script src="/walk/leaflet/leaflet.js" defer></script><script src="/walk/game.js" defer></script>
</head>
<body>
<header><div class="bar"><div class="brand"><span class="compass" aria-hidden="true">◈</span> WALK QUEST</div><span class="area"><i></i> 東京・渋谷</span></div></header>
<main>
<section id="home">
<div class="hero"><div class="intro"><span class="tag">渋谷フィールドガイド</span><h1>今日の散歩を、<br>小さな冒険に<span>。</span></h1><p>一枚の写真を手がかりに、<br>渋谷の街で同じ景色を探そう。</p></div>
<form class="card" id="setup"><h2>散歩の設定</h2>
<fieldset><legend>散歩できる時間 <small>往復の目安</small></legend><div class="choices"><label><input type="radio" name="minutes" value="30"><span>30 分</span></label><label><input type="radio" name="minutes" value="60" checked><span>60 分</span></label><label><input type="radio" name="minutes" value="90"><span>90 分</span></label></div></fieldset>
<fieldset><legend>探したい景色の時代</legend><div class="choices"><label><input type="radio" name="era" value="modern" checked><span>現代</span></label><label><input type="radio" name="era" value="showa"><span>昭和</span></label></div></fieldset>
<p id="eraNote" class="note" hidden>昭和の街角を見て、今も同じ方向を眺められる歩道を探します。</p><button class="primary" id="start">冒険を始める</button><button type="button" id="demo" class="secondary">渋谷デモを試す</button>
<small class="note">現地では位置情報を使います。デモはどこからでも遊べます。</small><p class="feedback" role="status" id="startMessage"></p></form></div>
<section class="guide"><h2>遊び方</h2><div class="steps"><div><b>01</b><p><strong>写真を見る</strong><br>時代を選んで、景色を記憶。</p></div><div><b>02</b><p><strong>街を歩く</strong><br>残りの距離を頼りに探索。</p></div><div><b>03</b><p><strong>ピンで答える</strong><br>地図上で場所を選び、答え合わせ。</p></div></div></section>
<details class="credits"><summary>写真クレジット</summary><div id="coverCredit">@include('walk-credit')</div></details>
</section>
<section id="play" hidden>
<div class="toolbar"><button class="link" id="quit">散歩の設定に戻る</button><span id="mode" class="tag"></span></div>
<div class="gamegrid"><div class="clue"><p class="eyeline" id="eraLabel"></p><h1 id="gameTitle" tabindex="-1">この景色を探そう</h1><div class="photo"><img id="clueImage" alt="探索の手がかりとなる写真"></div><small id="photoCredit"></small><p id="imageError" hidden>写真を読み込めませんでした。<button id="retryImage" class="link">再読み込み</button></p><div id="hintBox"><button class="secondary" id="hint">ヒントを見る</button><ol id="hints"></ol></div><div id="resultInfo" hidden><h2 id="spotName"></h2><p id="description"></p><small id="credit"></small></div></div>
<div class="mapcard"><div class="measure"><div><small id="distanceLabel">目的地までの直線距離</small><strong id="distance"></strong></div><div id="score" hidden></div></div>
<div id="map" aria-label="現在地と回答ピンの地図"></div>
<div class="controls"><p class="feedback" id="tileMessage" role="status"></p><p id="instruction"></p><button id="pinHere" class="secondary" hidden>現在地にピンを置く</button><p class="feedback" id="gameMessage" role="status"></p><button id="action" class="primary">回答する</button><button class="link" id="back" hidden>探索に戻る</button></div></div></div></section>
</main><footer><span>WALK QUEST</span><small>画面の操作は、立ち止まってから。</small></footer>
</body></html>