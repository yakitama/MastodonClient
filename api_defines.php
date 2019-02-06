<?php

// - - - - - - - - - - - - - - - - - - - -
//
// MastodonClient - PHP で Mastodon の API にアクセスするやつ
//   by yakitama
//
// api_defines.php		: API の URL を定義しているだけのファイルです
//
// - - - - - - - - - - - - - - - - - - - -

// 投稿を取得したり、作成したりする
define('APIURL_STATUSES', '/api/v1/statuses');
define('APIURL_TIMELINES_PUBLIC', '/api/v1/timelines/public');
