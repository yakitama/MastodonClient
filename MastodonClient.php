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
	private $error = FALSE;
	private $instance_baseurl = NULL;
	private $instance_apiurl = array();

	public function __construct ()
	{
		$construct_execution_error = FALSE;

		require_once('settings.php');

		// ----- ●インスタンス URL のバリデーション -----
		$this->instance_baseurl = $this->validate_instance_url(INSTANCE_URL);
		if ( $this->instance_baseurl === FALSE ) {
			$error = TRUE;
		}

		// ----- ●API URL を作成する -----
		require_once('api_defines.php');
		$this->instance_apiurl['statuses'] = $this->instance_baseurl.APIURL_STATUSES;
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
			$instance_url_httpsdetect = mb_strpos($instance_url_novalidate, array('https://', 'http://'));
			if ( $instance_url_httpsdetect === FALSE ) {
				// 含まれていない → 自動補完します
				$instance_url_novalidate = 'https://'.$instance_url_novalidate;
			}
			else if ( $instance_url_httpsdetect !== 0 ) {
				// 先頭じゃないところに含まれている → カバー不能な異常値です
				throw new Exception('INSTANCE_URL の指定が異常値です。');
			}
			else {
				// 先頭に含まれている → 正常値です
				// no operation
			}

			// 末尾が スラッシュ で終わっていないこと。終わっているなら削除する。
			$instance_url_slashdetect = mb_strpos($instance_url_novalidate, '/', mb_strlen($instance_url_novalidate)-1);
			if ( $instance_url_slashdetect === 0 ) {
				// 含まれている → スラッシュを削除する
				$instance_url_novalidate = mb_substr($instance_url_novalidate, 0, mb_strlen($instance_url_novalidate)-1);
			} 
		}
		catch (Exeption $e) {
			fprintf(STDERR, 'ERROR at '.__FUNCTION__.': '.$e->getMessage().PHP_EOL);
			return FALSE;
		}

		return $instance_url_novalidate;
	}
}
