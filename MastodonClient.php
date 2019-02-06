<?php

// - - - - - - - - - - - - - - - - - - - -
//
// MastodonClient - PHP で Mastodon の API にアクセスするやつ
//   by yakitama
//
// MastodonClient.php	: 使うときインクルードしてね
//
// 実行可能な PHP バージョン
//   PHP 7.0 より新しいもの
//
// 依存 PHP モジュール情報
//   - mbstring			: マルチバイト文字列を扱います。
//   - curl				: API にアクセスするときに curl を使います。
//
// - - - - - - - - - - - - - - - - - - - -

class MastodonClient {
	// const VISIBILITY_DEFAULT = 0;
	const VISIBILITY_PUBLIC = 'public';
	const VISIBILITY_UNLISTED = 'unlisted';
	const VISIBILITY_PRIVATE = 'private';
	const VISIBILITY_DIRECT = 'direct';

	private $error = FALSE;
	private $instance_baseurl = NULL;
	private $instance_apiurl = array();
	private $api_client_key = NULL;
	private $api_client_secret = NULL;
	private $api_access_token = NULL;

	public function __construct ()
	{
	}

	// function		init
	// 概要			インスタンスの初期設定をおこないます。
	// 引数			なし
	// 戻り値		異常であれば FALSE を返します。正常であれば TRUE を返します。
	// 制約			
	public function init ()
	{
		try {
			require_once('settings.php');

			// ----- ●インスタンス URL のバリデーション -----
			$this->instance_baseurl = $this->validate_instance_url(INSTANCE_URL);
			if ( $this->instance_baseurl === FALSE ) {
				throw new Exception('初期設定に失敗しました。');
			}
	
			// ----- ●Client Key とかの簡易バリデーション -----
			$this->api_client_key = CLIENT_KEY;
			$this->api_client_secret = CLIENT_SECRET;
			$this->api_access_token = ACCESS_TOKEN;
			if ( $this->validate_tokens($this->api_client_key, $this->api_client_secret, $this->api_access_token) === FALSE ) {
				throw new Exception('初期設定に失敗しました。');
			}
			
			// ----- ●API URL を作成する -----
			require_once('api_defines.php');
			$this->instance_apiurl['statuses'] = $this->instance_baseurl.APIURL_STATUSES;
			$this->instance_apiurl['timelines']['home'] = $this->instance_baseurl.APIURL_TIMELINES_HOME;
			$this->instance_apiurl['timelines']['public'] = $this->instance_baseurl.APIURL_TIMELINES_PUBLIC;
		}
		catch (Exception $e) {
			fprintf(STDERR, $e->getMessage().PHP_EOL);
			$this->error = TRUE;
			return FALSE;
		}

		return TRUE;
	}

	// function		post_statuses
	// 概要			トゥートします。
	// 引数			$visibility					公開範囲を指定します。VISIBILITY_ なんちゃらを使ってください。
	// 				$status						本文を指定します。
	// 				$spoiler_text				警告文を指定します。省略できます。この引数に1文字以上の文字列を指定すると、自動で CW フラグが設定されます。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、作成したステータスの URL を返します。
	// 制約			
	public function post_statuses ( $visibility, string $status, string $spoiler_text = "" )
	{
		try {
			// visibility のエラーチェック
			if ( $this->validate_visibility($visibility) === FALSE ) {
				throw new Exception('公開範囲の設定に誤りがあります。');
			}
			// 投稿テキストのエラーチェック
			if ( mb_strlen($status) == 0 ) {
				throw new Exception('投稿テキストに誤りがあります。');
			}

			// ペイロードの作成
			$payload = array();
			$payload['status'] = $status;
			$payload['visibility'] = $visibility;
			if ( mb_strlen($spoiler_text) > 0 ) {
				$payload['spoiler_text'] = $spoiler_text;
			}

			// cURL による POST リクエスト発行
			$curl_instance = curl_init($this->instance_apiurl['statuses']);
			curl_setopt($curl_instance, CURLOPT_POST, TRUE);
			curl_setopt($curl_instance, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$this->api_access_token));
			curl_setopt($curl_instance, CURLOPT_POSTFIELDS, $payload);
			if ( curl_exec($curl_instance) === FALSE ) {
				throw new Exception(curl_error($curl_instance));
			}
			curl_close($curl_instance);	
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}
		
	}

	// function		get_federation_timeline
	// 概要			FTL を取得します。
	// 引数			$count						取得件数を指定します。指定しない場合 50 件になります。
	// 				$start_id					ページめくりをするときは、その基準となるトゥート ID を指定します。
	//				$get_newer					より新しいトゥートを取得するときは TRUE を、より古いトゥートを取得するときは FALSE を指定します。
	// 				$media_only					メディア添付のあるトゥートだけを取得したい場合 TRUE を指定します。指定しない場合 FALSE になります。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、タイムラインのトゥートを適当に配列として返します。
	// 制約
	public function get_federation_timeline ( $count = 50, $start_id = NULL, $get_newer = FALSE, $media_only = FALSE )
	{
		return $this->get_public_timeline(FALSE, $count, $start_id, $get_newer, $media_only);
	}

	// function		get_local_timeline
	// 概要			LTL を取得します。
	// 引数			$count						取得件数を指定します。指定しない場合 50 件になります。
	// 				$start_id					ページめくりをするときは、その基準となるトゥート ID を指定します。
	//				$get_newer					より新しいトゥートを取得するときは TRUE を、より古いトゥートを取得するときは FALSE を指定します。
	// 				$media_only					メディア添付のあるトゥートだけを取得したい場合 TRUE を指定します。指定しない場合 FALSE になります。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、タイムラインのトゥートを適当に配列として返します。
	// 制約
	public function get_local_timeline ( $count = 50, $start_id = NULL, $get_newer = FALSE, $media_only = FALSE )
	{
		return $this->get_public_timeline(TRUE, $count, $start_id, $get_newer, $media_only);
	}

	// function		get_home_timeline
	// 概要			ホームタイムラインを取得します。
	// 引数			$count						取得件数を指定します。指定しない場合 20 件になります。
	// 				$start_id					ページめくりをするときは、その基準となるトゥート ID を指定します。
	//				$get_newer					より新しいトゥートを取得するときは TRUE を、より古いトゥートを取得するときは FALSE を指定します。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、タイムラインのトゥートを適当に配列として返します。
	// 制約
	public function get_home_timeline ( $count = 20, $start_id = NULL, $get_newer = FALSE )
	{
		try {
			// パラメータの作成
			$params = array();
			$params['limit'] = $count;
			if ( $start_id != NULL ) {
				if ( $get_newer ) {
					$params['since_id'] = $start_id;
				}
				else {
					$params['max_id'] = $start_id;
				}
			}

			// URL の作成
			$access_url = $this->instance_apiurl['timelines']['home'];
			$is_first_param = TRUE;
			foreach ( $params as $param => $value ) {
				if ( $is_first_param === TRUE ) {
					$access_url .= '?';
					$is_first_param = FALSE;
				} else {
					$access_url .= '&';
				}
				$access_url .= $param . "=" . $value;
			}

			// cURL による GET リクエスト発行
			$curl_instance = curl_init($access_url);
			curl_setopt($curl_instance, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$this->api_access_token));
			curl_setopt($curl_instance, CURLOPT_RETURNTRANSFER, TRUE);
			if ( ($result = curl_exec($curl_instance)) === FALSE ) {
				throw new Exception(curl_error($curl_instance));
			}
			curl_close($curl_instance);
			$json = json_decode($result, TRUE);
			return $json;
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}
	}

	// function		validate_instance_url
	// 概要			引数に指定された文字列がインスタンス URL として正常か検査します。
	// 引数			$instance_url_novalidate	検査したい文字列を指定します。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、インスタンス URL として正しい文字列を返します。
	// 制約			文字列の戻り値は curl に渡す URL として正常ですが、アクセスできるとは限りません。
	private function validate_instance_url ( string $instance_url_novalidate )
	{
		try {
			$instance_url_novalidate = trim($instance_url_novalidate);

			// 文字列が空っぽになったらエラー
			if ( mb_strlen($instance_url_novalidate) === 0 ) {
				throw new Exception('INSTANCE_URL が指定されていません。');
			}

			// 先頭が https:// または http:// で始まっていること。そうでないなら https:// を自動補完する。
			if ( (mb_strpos($instance_url_novalidate, 'https://') === 0) || (mb_strpos($instance_url_novalidate, 'http://') === 0) ) {
				// 先頭に含まれている → 正常値
				// no operation
			}
			else if ( (mb_strpos($instance_url_novalidate, 'https://') === FALSE) && (mb_strpos($instance_url_novalidate, 'http://') === FALSE) ) {
				// 含まれていない → 自動補完します
				$instance_url_novalidate = 'https://'.$instance_url_novalidate;
			}
			else if ( mb_strpos($instance_url_novalidate, 'https://') !== 0 ) {
				// 先頭じゃないところに https:// が含まれている → カバー不能な異常値です
				throw new Exception('INSTANCE_URL の指定が異常値です。');
			}
			else if ( mb_strpos($instance_url_novalidate, 'http://') !== 0 ) {
				// 先頭じゃないところに http:// が含まれている → カバー不能な異常値です
				throw new Exception('INSTANCE_URL の指定が異常値です。');
			}
			else {
				// それ以外の条件は存在しない
			}

			// 末尾が スラッシュ で終わっていないこと。終わっているなら削除する。
			$instance_url_slashdetect = mb_strpos($instance_url_novalidate, '/', mb_strlen($instance_url_novalidate)-1);
			if ( $instance_url_slashdetect === (mb_strlen($instance_url_novalidate)-1) ) {
				// 含まれている → スラッシュを削除する
				$instance_url_novalidate = mb_substr($instance_url_novalidate, 0, mb_strlen($instance_url_novalidate)-1);
			} 
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}

		return $instance_url_novalidate;
	}

	// function		validate_tokens
	// 概要			引数に指定された文字列がインスタンス URL として正常か検査します。
	// 引数			$client_key		検査したい文字列を指定します。
	// 				$cleint_secret	検査したい文字列を指定します。
	// 				$access_token	検査したい文字列を指定します。
	// 戻り値		異常であれば FALSE を返します。正常であれば TRUE を返します。
	// 制約			
	private function validate_tokens ( string $client_key, string $cleint_secret, string $access_token )
	{
		try {
			// いずれかの文字列の長さが 0 だったらエラー
			if ( strlen($client_key) == 0 ) {
				throw new Exception('CLIENT_KEY の指定が異常値です。');
			}
			if ( strlen($cleint_secret) == 0 ) {
				throw new Exception('CLIENT_SECRET の指定が異常値です。');
			}
			if ( strlen($access_token) == 0 ) {
				throw new Exception('ACCESS_TOKEN の指定が異常値です。');
			}
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}

		return TRUE;
	}

	// function		validate_visibility
	// 概要			公開範囲の設定が正しいか検査します。
	// 引数			$visibility					検査したい値を指定します。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、TRUE を返します。
	// 制約			
	private function validate_visibility ( $visibility )
	{
		try {
			if ( //($visibility !== $this::VISIBILITY_DEFAULT) &&
			     ($visibility !== $this::VISIBILITY_PUBLIC) &&
			     ($visibility !== $this::VISIBILITY_UNLISTED) &&
			     ($visibility !== $this::VISIBILITY_PRIVATE) &&
			     ($visibility !== $this::VISIBILITY_DIRECT) ) {
				throw new Exception('公開範囲に指定された値は範囲外です。');
			}
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}

		return TRUE;
	}

	// function		get_public_timeline
	// 概要			公開タイムラインを取得します。
	// 引数			$local						ローカルトゥートのみを取得します（ローカルタイムライン）。
	// 				$count						取得件数を指定します。
	// 				$start_id					ページめくりをするときは、その基準となるトゥート ID を指定します。先頭から取得する場合は NULL を指定します。
	//				$get_newer					より新しいトゥートを取得するときは TRUE を、より古いトゥートを取得するときは FALSE を指定します。
	// 				$media_only					メディア添付のあるトゥートだけを取得したい場合 TRUE を指定します。
	// 戻り値		異常であれば FALSE を返します。それ以外の場合、タイムラインのトゥートを適当に配列として返します。
	// 制約
	private function get_public_timeline ( $local, $count, $start_id, $get_newer, $media_only )
	{
		try {
			// パラメータの作成
			$params = array();
			$params['only_media'] = ($media_only === TRUE) ? 'true' : 'false';
			$params['limit'] = $count;
			if ( $start_id != NULL ) {
				if ( $get_newer ) {
					$params['since_id'] = $start_id;
				}
				else {
					$params['max_id'] = $start_id;
				}
			}
			if ( $local ) {
				$params['local'] = 'true';
			}

			// URL の作成
			$access_url = $this->instance_apiurl['timelines']['public'];
			$is_first_param = TRUE;
			foreach ( $params as $param => $value ) {
				if ( $is_first_param === TRUE ) {
					$access_url .= '?';
					$is_first_param = FALSE;
				} else {
					$access_url .= '&';
				}
				$access_url .= $param . "=" . $value;
			}

			// GET リクエスト発行
			$json = json_decode(file_get_contents($access_url), TRUE);
			return $json;
		}
		catch (Exception $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}
	}
}
