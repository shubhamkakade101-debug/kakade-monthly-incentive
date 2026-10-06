<?php
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'");header('X-Content-Type-Options: nosniff');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monthly Incentive • Team recognition</title><link rel="stylesheet" href="style.css?v=<?=filemtime(__DIR__.'/style.css')?>"></head><body><div id="app"></div><div id="toast" role="status"></div><script src="app.js?v=<?=filemtime(__DIR__.'/app.js')?>"></script></body></html>
