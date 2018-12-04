<?php
/**
 * @author Naomichi Yamakita <yamakita@dtx.co.jp>
 * @category mars
 * @package request
 * @version $Id: Mars_HttpRequestSender.class.php 3112 2011-10-16 19:00:30Z yamakita $
 * @copyright Copyright (c) 2006-2008 dt corporation
 */

/**
 * GET/POST ベースによる HTTP リクエストを発行します。
 * 
 * @author Naomichi Yamakita <yamakita@dtx.co.jp>
 * @category mars
 * @package request
 * @since 1.8.0
 */

class HttpRequestSender
{
  /**
   * POST データの送信形式。(JSON)
   */
  const FORMAT_JSON = 'application/json';

  /**
   * MIME 定数。(application/x-www-form-urlencoded)
   */
  const CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED = 'application/x-www-form-urlencoded';

  /**
   * MIME 定数。(multipart/form-data)
   */
  const CONTENT_TYPE_MULTIPART_FORM_DATA = 'multipart/form-data';

  const REQUEST_SEND_TYPE_FGC = 'file_get_contents';
  const REQUEST_SEND_TYPE_CURL = 'curl';

  /**
   * リクエスト基底 URI。
   */
  private $_baseURI;

  /**
   * リクエストパス。
   * @var string
   */
  private $_requestPath;

  /**
   * リクエストメソッド。(Mars_HttpRequest::HTTP_*)
   * @var string
   */
  private $_requestMethod = 'GET';

  /**
   * ユーザエージェント。
   * @var string
   */
  private $_userAgent;

  /**
   * stream_context_create() のオプションリスト。
   * @var array
   */
  private $_options = array();

  /**
   * 最大リダイレクト回数。
   * @var int
   */
  private $_maxRedirect = 2;

  /**
   * HTTP プロトコルバージョン。
   * @var string
   */
  private $_protocolVersion = "1.0";

  /**
   * 読み込みタイムアウト秒。
   * @var int
   */
  private $_readTimeout = 10;

  /**
   * 送信ヘッダリスト。
   * @var array
   */
  private $_headers = array();

  /**
   * 送信クエリパラメータ。
   * @var array
   */
  private $_parameters;

  /**
   * 送信ファイル。
   * @var array
   */
  private $_files;

  /**
   * POST データの送信フォーマット。
   * @var string
   */
  private $_postFormat;

  /**
   * POST データのエンコーディング形式。
   * @var string
   */
  private $_postEncoding = self::CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED;

  /**
   * 応答ヘッダリスト。
   * @var array
   */
  private $_responseHeaders = array();

  /**
   * コンストラクタ。
   * 
   * @param string $baseURI リクエスト対象の基底 URI。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function __construct($baseURI = NULL)
  {
    if ($baseURI !== NULL) {
      $this->setBaseURI($baseURI);
    }

    $this->clearHeader();
    $this->clearParameter();
    $this->clearUploadFile();
  }

  /**
   * リクエスト対象の基底 URI を設定します。
   * 
   * @param string $baseURI リクエスト対象の基底 URI。
   * @param bool $entityDecode baseURI に含まれる HTML エンティティを適切な文字に変換する場合は TRUE を指定。
   *   TRUE を指定した場合、例えば baseURI 内の "&" は "&amp;" に変換されます。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setBaseURI($baseURI, $entityDecode = FALSE)
  {
    if ($entityDecode) {
      $baseURI = html_entity_decode($baseURI, ENT_QUOTES, default_encoding(ENCODING_TYPE_INPUT));
    }

    $this->_baseURI = $baseURI;

    return $this;
  }

  /**
   * {@link setBaseURI()} メソッドで指定した基底 URI に対するリクエストパスを指定します。
   * 例えば setBaseURI('http://mars/') が指定された状態で setRequestPath('manager/login.do') を指定した場合、実際に発行されるリクエスト URI は 'http://mars/manager/login.do' となります。
   * <i>このメソッドで指定したパスは、{@link send()} メソッドがコールされた時点で自動的に破棄されます。</i>
   * 
   * @param string $request リクエストパス。
   * @param bool $entityDecode {@link setBaseURI()} メソッドを参照。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.5
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setRequestPath($requestPath, $entityDecode = FALSE)
  {
    if ($entityDecode) {
      $requestPath = html_entity_decode($requestPath, ENT_QUOTES, default_encoding(ENCODING_TYPE_INPUT));
    }

    $this->_requestPath = $requestPath;

    return $this;
  }

  /**
   * リクエストメソッドを設定します。
   * 
   * @param int $requestMethod {@link Mars_HttpRequest::HTTP_GET}、または {@link Mars_HttpRequest::HTTP_POST} を指定。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setRequestMethod($requestMethod)
  {
    $this->_requestMethod = $requestMethod;
    return $this;
  }

  /**
   * BASIC 認証を通過するための認証情報をセットします。
   * 
   * @param string $user ユーザ名。
   * @param string $password パスワード。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setBasicAuthorization($user, $password)
  {
    $authorization = 'Basic ' . base64_encode($user . ':' . $password);
    $this->addHeader('Authorization', $authorization);

    return $this;
  }

  /**
   * ユーザエージェントを設定します。
   * 
   * @param string $userAgent ユーザエージェント。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setUserAgent($userAgent)
  {
    $this->_userAgent = $userAgent;
    $this->addHeader('User-Agent', $userAgent);

    return $this;
  }

  /**
   * 受け入れ可能なメディアタイプを設定します。
   * 
   * @param string $value 受け入れ可能なメディアタイプ。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.7
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setAccept($value)
  {
    $this->addHeader('Accept', $value);

    return $this;
  }

  /**
   * 受け入れ可能な文字セットを設定します。
   * 
   * @param string $value 受け入れ可能な文字セット。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.7
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setAcceptCharset($value)
  {
    $this->addHeader('Accept-Charset', $value);

    return $this;
  }

  /**
   * 受け入れ可能なコンテンツのエンコーディングを設定します。
   * 
   * @param string $value 受け入れ可能なコンテンツのエンコーディング。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.7
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setAcceptEncoding($value)
  {
    $this->addHeader('Accept-Encoding', $value);

    return $this;
  }

  /**
   * 受け入れ可能な言語を設定します。
   * 
   * @param string $value 受け入れ可能な言語。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.7
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setAcceptLanguage($value)
  {
    $this->addHeader('Accept-Language', $value);

    return $this;
  }

  /**
   * 経由するプロキシサーバを設定します。
   * 
   * @param string $host プロキシホスト名。
   * @param int $port プロキシポート番号。
   * @param bool $requestFullURI TRUE を設定した場合、リクエスト発行時に完全な URI が利用されます。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setProxy($host, $port, $requestFullURI = TRUE)
  {
    $this->_options['http']['proxy'] = 'tcp://' . $host . ':' . $port;
    $this->_options['http']['request_fulluri'] = $requestFullURI;

    return $this;
  }

  /**
   * 接続先のページがリダイレクトレスポンスを返した際、再リダイレクト要求を行う最大回数を設定します。
   * 
   * @param int $maxRedirect 最大リダイレクト回数。既定値は 2 回。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setMaxRedirect($maxRedirect)
  {
    $this->_maxRedirect = $maxRedirect;

    return $this;
  }

  /**
   * レスポンスヘッダに Location が含まれる場合、ヘッダに記された URI にリダイレクトするかどうかを設定します。
   * 既定の動作では URI をたどります。
   * 
   * @param bool $followLocation Location をたどる場合は TRUE、たどらない場合は FALSE を指定。
   *   FALSE 指定時の動作は {@link setMaxRedirect()} メソッドに 0 を指定した場合と同じ挙動になります。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.10.0
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setFollowLocation($followLocation)
  {
    $this->_options['http']['follow_location'] = $followLocation;

    return $this;
  }

  /**
   * リクエスト要求時における HTTP プロトコルのバージョンを設定します。
   * 
   * @param float $protocolVersion プロトコルバージョンの指定。既定値は 1.1。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setProtocolVersion($protocolVersion)
  {
    $this->_protocolVersion = $protocolVersion;

    return $this;
  }

  /**
   * 読み込みタイムアウト秒を設定します。
   * 
   * @param float $readTimeout タイムアウト秒数。既定値は 10 秒。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setReadTimeout($readTimeout)
  {
    $this->_readTimeout = $readTimeout;

    return $this;
  }

  /**
   * サーバに送信する Cookie 情報を追加します。
   * 
   * @param string $name Cookie 名。
   * @param string $value Cookie の値。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function addCookie($name, $value)
  {
    $value = $name . '=' . $value;

    if (isset($this->_headers['Cookie'])) {
      $this->_headers['Cookie'] .= '; ' . $value;
    } else {
      $this->_headers['Cookie'] = $value;
    }

    return $this;
  }

  /**
   * キー、値の組ではない生の Cookie を設定します。
   * 
   * @param string $value Cookie データ。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.6
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setRawCookie($value)
  {
    $this->_headers['Cookie'] = $value;

    return $this;
  }

  /**
   * 登録されている Cookie を削除します。
   * 
   * @param string $name 削除対象の Cookie 名。未指定の場合は登録済みの全ての Cookie を削除します。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since  1.8.6
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function clearCookie($name = NULL)
  {
    if ($name === NULL) {
      $this->clearHeader('Cookie');

    } else {
      $headers = explode(';', $this->_headers['Cookie']);
      $buffer = NULL;

      foreach ($headers as $header) {
        $header = trim($header);

        if (strpos($header, $name . '=') !== 0) {
          $buffer .= $header . '; ';
        }
      }

      $this->_headers['Cookie'] = $buffer;
    }

    return $this;
  }

  /**
   * サーバに送信する HTTP ヘッダを追加します。
   * 
   * @param string $name HTTP ヘッダ名。
   * @param mixed $value HTTP ヘッダ値。既に同一のヘッダが登録されている場合は値が上書きされます。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function addHeader($name, $value)
  {
    $this->_headers[$name] = $value;

    return $this;
  }

  /**
   * 登録されているヘッダを削除します。
   * 
   * @param string $name 削除対象のヘッダ名。未指定の場合は登録済みの全てのヘッダを削除します。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function clearHeader($name = NULL)
  {
    if ($name === NULL) {
      $this->_headers = array();
      $userAgent = ini_get('user_agent');

      if (strlen($userAgent) == 0) {
        $userAgent = 'PHP';
      }

      $this->setUserAgent($userAgent);

    } else {
      unset($this->_headers[$name]);
    }

    return $this;
  }

  /**
   * サーバに送信するリクエストパラメータを追加します。
   * 
   * @param string $name リクエストパラメータ名。
   * @param string $value リクエストパラメータ値。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function addParameter($name, $value)
  {
    $this->_parameters[$name] = $value;

    return $this;
  }

  /**
   * サーバに送信するリクエストパラメータを連想配列形式で追加します。
   * 
   * @param array $parameters 連想配列形式のリクエストパラメータ。
   * @since 1.9.0
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function addParameters($parameters)
  {
    foreach ($parameters as $name => $value) {
      $this->_parameters[$name] = $value;
    }

    return $this;
  }

  /**
   * 登録されているリクエストパラメータを削除します。
   * 
   * @param string $name 削除対象のパラメータ名。未指定の場合は登録済みの全てのパラメータを削除します。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function clearParameter($name = NULL)
  {
    if ($name === NULL) {
      $this->_parameters = array();
    } else {
      unset($this->_parameters[$name]);
    }

    return $this;
  }

  /**
   * サーバに送信するファイルを追加します。
   * 
   * @param string $name リクエストパラメータ名。
   * @param string $filePath 送信するファイルのパス。APP_ROOD_DIR からの相対パス、あるいは絶対パスが有効。
   *   パスが未指定の場合はパラメータのみ送信され、ファイルのアップロードはないものと見なされます。
   * @param string $mimeType ファイルの MIME タイプ。未指定時は {@link finfo_file()} 関数により MIME タイプが自動識別されます。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.5
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function addUploadFile($name, $filePath = NULL, $mimeType = NULL)
  {
    $this->setPostEncoding(self::CONTENT_TYPE_MULTIPART_FORM_DATA);

    if ($filePath === NULL) {
      $this->_files[$name] = array();

    } else {
      if (!is_absolute_path($filePath)) {
        $filePath = absolute_path($filePath);
      }

      $this->_files[$name]['path'] = $filePath;
      $this->_files[$name]['type'] = $mimeType;
    }

    return $this;
  }

  /**
   * 登録されているファイルデータを削除します。
   * 
   * @param string $name 削除対象のパラメータ名。未指定の場合は登録済みの全てのファイルを削除します。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.5
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function clearUploadFile($name = NULL)
  {
    if ($name === NULL) {
      $this->_files = array();
    } else {
      unset($this->_files[$name]);
    }

    return $this;
  }

  /**
   * POST データの送信フォーマットを設定します。
   * 
   * @param string $postFormat FORMAT_*** 定数を指定。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.8.7
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setPostFormat($postFormat)
  {
    if ($postFormat === self::FORMAT_JSON) {
      $this->addHeader('Content-Type', $postFormat);
      $this->_postFormat = $postFormat;

    } else {
      $message = sprintf('Format is not supported.', $postFormat);
      throw new Mars_UnsupportedException($message);
    }

    return $this;
  }

  /**
   * POST データのエンコード方法を設定します。
   * {@link addUploadFile()} メソッドがコールされた場合、データのエンコーディング形式は 'multipart/form-data' に固定となります。-
   *
   * @param string $postEncoding MIME_TYPE_* 定数を指定。既定値は {@link CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED}。
   * @return Mars_HttpRequestSender Mars_HttpRequestSender オブジェクトを返します。
   * @since 1.12.2
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */
  public function setPostEncoding($postEncoding)
  {
    $this->_postEncoding = $postEncoding;

    return $this;
  }

  /**
   * リクエストする URI を構築します。
   *
   * @return string リクエストする URI を返します。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   * @since 1.10.5
   */
  public function buildRequestURI()
  {
    if (!$this->_requestPath) {
      $requestURI = $this->_baseURI;

    } else {
      $baseURI = rtrim($this->_baseURI, '/');

      if (substr($this->_requestPath, 0, 1) !== '/') {
        $requestPath = '/' . $this->_requestPath;
      } else {
        $requestPath = $this->_requestPath;
      }

      $requestURI = $baseURI . $requestPath;
    }
    if ($this->_requestMethod == 'GET') {
      // '&' の指定がないと Windows 環境で '&' が '&amp;' に変換される
      $data = http_build_query($this->_parameters, '', '&');

      if (strpos($requestURI, '?') !== FALSE) {
        $requestURI .= '&' . $data;
      } else {
        $requestURI .= '?' . $data;
      }
    }

    return $requestURI;
  }

  /**
   * サーバにリクエストを送信します。
   * 
   * @return Mars_HttpResponseParser {@link Mars_HttpResponseParser} のオブジェクトインスタンスを返します。
   * @throws Mars_ConnectException 指定された URI に接続できなかった場合に発生。
   * @author Naomichi Yamakita <yamakita@dtx.co.jp>
   */

  public function send($requestSendType = self::REQUEST_SEND_TYPE_FGC)
  {
    $parameters = $this->_parameters;
    $requestURI = $this->buildRequestURI();
    $parse = parse_url($requestURI);

    if (empty($parse['host'])) {
      throw new Exception('Request URI is not set');
    }
    // ヘッダの構築
    $headers = array();
    $headers[] = sprintf('Host: %s', $parse['host']);

    if (sizeof($parameters)) {
      switch ($this->_requestMethod) {
        case 'GET';
          $data = http_build_query($parameters, '', '&');
          break;

        case 'POST';

          if ($this->_postEncoding === self::CONTENT_TYPE_MULTIPART_FORM_DATA) {
            $data = NULL;
            $boundary = str_repeat('-', 20) . build_rand_str();

            // POST データパートの生成
            foreach ($parameters as $name => $value) {
              $data .= sprintf("--%s\r\n"
                              ."Content-Disposition: form-data; name=\"%s\""
                              ."\r\n\r\n"
                              ."%s\r\n",
                               $boundary,
                               $name,
                               $value);

            }

            // ファイルが含まれる場合
            $fileSize = sizeof($this->_files);

            if ($fileSize) {
              $count = 0;

              foreach ($this->_files as $fileName => $attributes) {
                $count++;
                $hasUploadFile = TRUE;

                if (sizeof($attributes)) {
                  if ($attributes['type'] === NULL) {
                    $info = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($info, $attributes['path']);
                    finfo_close($info);

                  } else {
                    $mimeType = $attributes['type'];
                  }

                  $data .= sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n"
                                  ."Content-Type: %s\r\n"
                                  ."Content-Transfer-Encoding: binary\r\n\r\n"
                                  ."%s\r\n",
                                  $fileName,
                                  basename($attributes['path']),
                                  $mimeType,
                                  file_get_contents($attributes['path']));
                } else {
                  $data .= sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"\"\r\n"
                                  ."Content-Type: application/octet-stream\r\n\r\n\r\n",
                                  $fileName);
                }

                if ($count == $fileSize) {
                  $data .= sprintf("--%s--\r\n", $boundary);
                } else {
                  $data .= sprintf("--%s\r\n", $boundary);
                }

                $this->_options['http']['content'] = $data;
              }
            } else {
              $data .= sprintf("--%s--\r\n", $boundary);
              $this->_options['http']['content'] = $data;
            }
            break;
          }
        // HTTP_POST (ファイルなし) の場合も実行される
        case 'PUT':
          switch ($this->_postFormat) {
            case self::FORMAT_JSON:
              $internalEncoding = default_encoding(ENCODING_TYPE_INTERNAL);

              if ($internalEncoding !== 'UTF-8') {
                mb_convert_variables('UTF-8', $internalEncoding, $parameters);
              }

              // see: http://bugs.php.net/bug.php?id=49366
              $data = str_replace('\/', '/', json_encode($parameters));
              break;

            default:
              $data = http_build_query($parameters, '', '&');
              break;
          }

          $this->_options['http']['content'] = $data;
          $headers[] = sprintf('Content-Length: %d', strlen($data));

          break;
      }
    }

    // Content-Type の指定
    $hasContentType = FALSE;
    foreach ($this->_headers as $name => $value) {
      $headers[] = sprintf('%s: %s', $name, $value);

      if (strcasecmp($name, 'Content-Type') === 0) {
        $hasContentType = TRUE;
      }
    }

    // ヘッダに Content-Type が含まれない場合はデフォルト値を指定
    if (!$hasContentType) {
      if ($this->_requestMethod === 'POST') {
        $headers[] = sprintf('Content-Type: %s; boundary=%s', $this->_postEncoding, $boundary);
      } else {
        $headers[] = sprintf('Content-Type: %s', self::CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED);
      }
    }
    // 'header' に関しては添字形式の配列が指定可能とマニュアルにあるが、環境によっては正常に動作しなかったので文字列として格納
    $this->_options['http']['header'] = implode("\r\n", $headers);

    $this->_options['http']['method'] = $this->_requestMethod;
    $this->_options['http']['max_redirects'] = $this->_maxRedirect;

    $this->_options['http']['protocol_version'] = $this->_protocolVersion;
    $this->_options['http']['follow_location'] = 0;
    $this->_options['http']['timeout'] = $this->_readTimeout;
    $this->_options['http']['ignore_errors'] = TRUE; // 4xx、5xx エラーは検知できるが、ホスト名の存在チェックは行われない

    if ($requestSendType == self::REQUEST_SEND_TYPE_CURL) {
      $ch = curl_init($requestURI);

      curl_setopt($ch, CURLOPT_NOBODY, TRUE); // HEADリクエストにする
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
      curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
      curl_setopt($ch, CURLOPT_USERAGENT, $this->_userAgent);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_options['http']['content']);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, TRUE); // ヘッダも出力したい場合
      curl_setopt($ch, CURLOPT_COOKIE, $headers['cookie']);
      unset($headers['cookie']);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);

      $res = curl_exec($ch);
      if ($this->_postEncoding == self::CONTENT_TYPE_MULTIPART_FORM_DATA) {
      }
      $res = explode("\n", $res);
      $data = '';
      $blankFlag = FALSE;
      foreach ($res as $val) {
        $val = rtrim($val);
        if ($val && !$blankFlag) {
          $responseHeader[] = $val;
        } else if (!$blankFlag) {
          $blankFlag = TRUE;
        } else if ($blankFlag) {
          $data .= $val . "\n";
        }
      }
      $data = rtrim($data);
      curl_close($ch);
    }

    if (!$responseHeader) {
      $context = stream_context_create($this->_options);
      $data = @file_get_contents($requestURI, FALSE, $context);
      $responseHeader = $http_response_header;
    }
    if ($data !== FALSE) {
      return new HttpResponseParser($responseHeader, $data);

    } else {
      $message = sprintf('Could not connect to the specified host. [%s]', $this->_baseURI);
      throw new Exception($message);
    }
  }
}
