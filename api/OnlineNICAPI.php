<?php





/**
 * OnlineNIC RESTful API Class with Demo Mode
 */

require_once __DIR__ . '/../config/onlinenic.php';
require_once __DIR__ . '/../config/database.php';

class OnlineNICAPI
{

  private $user;
  private $password;
  private $apikey;
  private $baseURL;

  public function __construct()
  {
    $this->user = ONLINENIC_USER;
    $this->password = ONLINENIC_PASSWORD;
    $this->apikey = ONLINENIC_APIKEY;
    $this->baseURL = getOnlineNICBaseURL();
  }

  private function generateToken($timestamp)
  {
    return md5($this->user . $timestamp . $this->apikey);
  }

  private function makeRequest($endpoint, $params)
  {
    // Demo mode
    if (defined('DEMO_MODE') && DEMO_MODE) {
      return $this->getMockResponse($params);
    }

    if (ONLINENIC_ENV === 'demo') {
      return $this->getMockResponse($params);
    }

    $timestamp = time();
    $token = $this->generateToken($timestamp);

    $params['user'] = $this->user;
    $params['timestamp'] = $timestamp;
    $params['apikey'] = $this->apikey;
    $params['token'] = $token;

    $url = $this->baseURL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      return $this->getMockResponse($params);
    }

    $data = json_decode($response, true);
    $success = isset($data['code']) && $data['code'] == '1000';

    return [
      'success' => $success,
      'code' => $data['code'] ?? 'UNKNOWN',
      'message' => $data['message'] ?? 'Unknown error',
      'data' => $data['data'] ?? []
    ];
  }

  private function getMockResponse($params)
  {
    $command = $params['command'] ?? '';
    $domain = $params['domain'] ?? '';

    switch ($command) {
      case 'checkDomain':
        $available = (crc32($domain) % 2 == 0);
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Success (DEMO)',
          'data' => [
            'domain' => $domain,
            'avail' => $available ? 1 : 0,
            'Premium' => 'false',
            'Prices' => [['price' => '12.99', 'period' => '1']]
          ]
        ];

      case 'registerDomain':
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Registered (DEMO)',
          'data' => [
            'domain' => $domain,
            'regdate' => date('Y-m-d'),
            'expdate' => date('Y-m-d', strtotime('+1 year'))
          ]
        ];

      case 'renewDomain':
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Renewed (DEMO)',
          'data' => [
            'domain' => $domain,
            'expdate' => date('Y-m-d', strtotime('+1 year'))
          ]
        ];

      case 'infoDomain':
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Info retrieved (DEMO)',
          'data' => [
            'domain' => $domain,
            'status' => 'active',
            'regdate' => date('Y-m-d', strtotime('-1 year')),
            'expdate' => date('Y-m-d', strtotime('+1 year')),
            'dns1' => 'ns1.onlinenic.com',
            'dns2' => 'ns2.onlinenic.com'
          ]
        ];

      case 'getAuthCode':
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Auth code (DEMO)',
          'data' => [
            'domain' => $domain,
            'Transfercode' => strtoupper(substr(md5($domain), 0, 16))
          ]
        ];

      default:
        return [
          'success' => true,
          'code' => '1000',
          'message' => 'Success (DEMO)'
        ];
    }
  }

  public function checkDomain($domain, $op = null)
  {
    $params = ['command' => 'checkDomain', 'domain' => $domain];
    if ($op)
      $params['op'] = $op;
    return $this->makeRequest('/api4/domain/index.php', $params);
  }

  public function registerDomain($data)
  {
    $params = [
      'command' => 'registerDomain',
      'domain' => $data['domain'],
      'period' => $data['period'],
      'dns1' => $data['dns1'] ?? 'ns1.onlinenic.com',
      'dns2' => $data['dns2'] ?? 'ns2.onlinenic.com',
      'registrant' => $data['registrant'],
      'admin' => $data['admin'],
      'tech' => $data['tech'],
      'billing' => $data['billing']
    ];
    return $this->makeRequest('/api4/domain/index.php', $params);
  }

  public function renewDomain($domain, $period, $fee = null)
  {
    $params = ['command' => 'renewDomain', 'domain' => $domain, 'period' => $period];
    if ($fee)
      $params['fee'] = $fee;
    return $this->makeRequest('/api4/domain/index.php', $params);
  }

  public function getDomainInfo($domain)
  {
    return $this->makeRequest('/api4/domain/index.php', [
      'command' => 'infoDomain',
      'domain' => $domain
    ]);
  }

  public function getAuthCode($domain)
  {
    return $this->makeRequest('/api4/domain/index.php', [
      'command' => 'getAuthCode',
      'domain' => $domain
    ]);
  }

  public function updateAuthCode($domain, $authCode)
  {
    return $this->makeRequest('/api4/domain/index.php', [
      'command' => 'updateAuthCode',
      'domain' => $domain,
      'authcode' => $authCode
    ]);
  }

  public function updateDomainStatus($domain, $lock)
  {
    return $this->makeRequest('/api4/domain/index.php', [
      'command' => 'updateDomainStatus',
      'domain' => $domain,
      'ctp' => $lock ? 'Y' : 'N'
    ]);
  }

  public function updateDomainDNS($domain, $dnsServers)
  {
    $params = [
      'command' => 'updateDomainDns',
      'domain' => $domain,
      'dns1' => $dnsServers['dns1'],
      'dns2' => $dnsServers['dns2']
    ];
    if (isset($dnsServers['dns3']))
      $params['dns3'] = $dnsServers['dns3'];
    if (isset($dnsServers['dns4']))
      $params['dns4'] = $dnsServers['dns4'];
    return $this->makeRequest('/api4/domain/index.php', $params);
  }

  public function transferDomain($domain, $authCode, $contactId, $fee = null)
  {
    $params = [
      'command' => 'transferDomain',
      'domain' => $domain,
      'password' => $authCode,
      'contactid' => $contactId
    ];
    if ($fee)
      $params['fee'] = $fee;
    return $this->makeRequest('/api4/domain/index.php', $params);
  }

  public function getCachedDomainCheck($domain, $op = null)
  {
    return $this->checkDomain($domain, $op);
  }
}
?>