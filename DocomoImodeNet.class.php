<?php
/**
 * @author Hiroshi Kouda <kouda@dtx.co.jp>
 * @package docomoimodenet
 * @version $Id: $
 * @copyright Copyright (c) 2012 dt corporation
 */

require_once('HttpRequestSender.class.php');
require_once('HttpResponseParser.class.php');
require_once('Crypter.class.php');
require_once('setting.php');
require_once('srand_php_fw.php');

$no_auth = 1;
require_once('mod_common.php');

class DocomoImodeNet
{

  private $_rawCookie;
  private $_rawCookiePwsp2;
  private $_cookiePwsp2;
  private $_sender;
  private $_parser;
  private $_docomoId;
  private $_password;
  private $_mailAddress;
  private $_subject;
  private $_body;
  private $_cookieFile;

  /**
   * コンストラクタ。
   * 
   * @param 
   * @author 
   */
  public function __construct()
  {
  }

  public function getCookieFile()
  {
    require('/var/www/photon/common/setting.php');
    $cookieFile = $FW_CONF['base_dir'] . '/tmp/cookie_' . $this->_docomoId;
    return $cookieFile;
  }

  public function setDocomoId($docomoId)
  {
    $this->_docomoId = $docomoId;
    return $this;
  }

  public function setPassword($password)
  {
    $crypter = new Crypter();
    $this->_password = $crypter->decrypt($password);
    return $this;
  }

  public function setMailAddress($mailAddress)
  {
    $this->_mailAddress = $mailAddress;
    return $this;
  }

  public function setSubject($subject)
  {
    $this->_subject = $subject;
    return $this;
  }

  public function setBody($body)
  {
    $this->_body = $body;
    return $this;
  }

  public function login()
  {
    $formatId =  changeId($this->_docomoId);
    $cookieDocomoId = 'MDCM_DCMID='. $formatId . ';';

    $tmpCookieFile = $this->getCookieFile();
    if (is_file($tmpCookieFile)) {
     $this->_rawCookiePwsp2 = trim(file_get_contents($tmpCookieFile));
    }

    $sender = new HttpRequestSender();
    $sender->setProtocolVersion(1.1);
    $sender->setBaseURI('https://imode.net/');
    $sender->setRequestPath('/dcm/dfw');
    $sender->setMaxRedirect(0);
    $sender->setRequestMethod('POST');
    $sender->addParameter("HIDEURL","?WM_AK=https%3a%2f%2fimode.net%2fag&path=%2fimail%2ftop&query=");
    $sender->addParameter("LOGIN","WM_LOGIN");
    $sender->addParameter("WM_KEY","0");
    $sender->addParameter("MDCM_UID",$this->_docomoId);
    $sender->addParameter("MDCM_PWD",$this->_password);
    $sender->setUserAgent("Mozilla/4.0 (compatible;MSIE 7.0; Windows NT 6.0;)");

    $sender->addHeader('X-PW-SERVICE', 'PCMAIL/1.0');
    $sender->addHeader('Referer', 'https://imode.net/imail/oexaf/ahtm/index2.html');
    if (!$this->_rawCookiePwsp2) {
      $parser = $sender->send($sender::REQUEST_SEND_TYPE_CURL);
      $sender->clearParameter();
      $cookies = $parser->getCookie('WM_IW_INFO_PA');
      $rawCookie = $cookieDocomoId . ' ' . 'WM_IW_INFO_PA=' . $cookies . '; WM_IW_INFO=' . $cookies;

      $requestPath = '/imail/oexaf/acgi/login';
      $sender->addHeader('X-PW-SERVICE', 'PCMAIL/1.0');
      $sender->addHeader('Referer', 'https://imode.net/imail/oexaf/ahtm/index2.html');
      $sender->setRequestPath($requestPath);
      $sender->setRawCookie($rawCookie);
      $parser = $sender->send($sender::REQUEST_SEND_TYPE_CURL);
      $cookiePwsp2 = $parser->getCookie('pwsp2');
      $this->_rawCookiePwsp2 = 'pwsp2=' . $cookiePwsp2 . '; ' . $rawCookie;
      file_put_contents($tmpCookieFile, $this->_rawCookiePwsp2);
    }
    $sender->clearParameter();

    $this->_sender = $sender;
  }

  public function mailSend()
  {
    $sender = $this->_sender;
    $requestPath = '/imail/oexaf/acgi/mailsend';
    $sender->addParameter("folder.id", 0);
    $sender->addParameter("folder.mail.id", '0000000000000000000');
    $sender->addParameter("folder.mail.type", 0);
    $sender->addParameter("folder.mail.addrinfo(0).mladdr", $this->_mailAddress);
    $sender->addParameter("folder.mail.addrinfo(0).type", 1);
    $sender->addParameter("folder.mail.subject", $this->_subject);
    $sender->addParameter("folder.mail.data", $this->_body);

    // F9AE -
    $sender->addParameter("iemoji(0).id", '');
    // F995 wa-i
    $sender->addParameter("iemoji(1).id", '');
    $sender->addParameter("reqtype", 0);
    $sender->addHeader('X-PW-SERVICE', 'PCMAIL/1.0');
    $sender->addHeader('Referer', 'https://imode.net/imail/oexaf/ahtm/index_f.html');
    $sender->addHeader('Expect', '');
    $sender->setPostEncoding($sender::CONTENT_TYPE_MULTIPART_FORM_DATA);
    $sender->setRequestPath($requestPath);
    $sender->setRawCookie($this->_rawCookiePwsp2);
    $parser = $sender->send($sender::REQUEST_SEND_TYPE_CURL);
    $sender->clearParameter();
    $json = $parser->getContents();
    $result = formatJson($json);

    // if (!$result || $result['common']['result'] == 'PW1409') {
    //   $tmpCookieFile = $this->getCookieFile();
    //   unlink($tmpCookieFile);
    //   $this->_rawCookiePwsp2 = NULL;
    // }

    return $result['common']['result'];
  }

  public function getMailIdList()
  {
    $sender = $this->_sender;
    $requestPath = '/imail/oexaf/acgi/mailidlist';
    $sender->addParameter("folder(0).id", 0);
    $sender->addHeader('X-PW-SERVICE', 'PCMAIL/1.0');
    $sender->addHeader('Referer', 'https://imode.net/imail/oexaf/ahtm/index_f.html');
    $sender->setRequestPath($requestPath);
    $sender->setRawCookie($this->_rawCookiePwsp2);
    $parser = $sender->send($sender::REQUEST_SEND_TYPE_CURL);
    $sender->clearParameter();

    $json = $parser->getContents();
    $res = formatJson($json);
    if (!$res || $res['common']['result'] != 'PW1000') {
      $tmpCookieFile = $this->getCookieFile();
      unlink($tmpCookieFile);
      $this->_rawCookiePwsp2 = NULL;
    }
    $mailIdList = $res['data']['folderList'][0]['mailIdList'];

    sort($mailIdList);
    return $mailIdList;
  }

  public function getMailDetail($mailId)
  {
    $sender = $this->_sender;
    $requestPath = '/imail/oexaf/acgi/maildetail';
    $sender->setRequestPath($requestPath);
    $sender->setRawCookie($this->_rawCookiePwsp2);
    $sender->addParameter("folder.id", 0);
    $sender->addParameter("folder.mail.id", $mailId);
    $parser = $sender->send($sender::REQUEST_SEND_TYPE_CURL);
    $json = $parser->getContents();

    $res = formatJson($json);

    return $res;
  }
}
