<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\bansystem\BanList;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class RustCheatCheck
{
    private $_banList = [];

    public function banList() {
        self::serverBans();
        return $this->_banList;
    }

    public function update() {
        $key = $this->getKey();
        if (empty($key)) {
            $this->auth();
            $key = $this->getKey();
        }
        for ($i = 0; $i < 239; $i++) {
            $count = 1000;
            try {
                sleep(2);
                $banList = $this->list($i * $count, $count, $key);
                foreach ($banList as $item) {
                    BanList::createModel(
                        $item['steam_id'],
                        $item['project'],
                        $item['server'],
                        $item['reason'],
                        $item['date'],
                        $item['expireDate'],
                    );
                }
            } catch (\Exception $ex) {
                echo "i = $i" . PHP_EOL;
                echo "count = $count" . PHP_EOL;
                echo $ex->getMessage() . PHP_EOL;
            }
        }
    }

    private function list($offset, $count, $key) {
        $result = [];
        $banList = $this->getBanList($offset, $count, $key);
        foreach ($banList as $item) {
            $serverName = null;
            $projectName = $item['serverName'];
            if ($projectName == 'GGRust') {
                continue;
            }
            if ($projectName == 'RustDom') {
                $projectName = "Простой";
            }
            if ($projectName == "MAGIC RUST") {
                $projectName = "Magic Rust";
            }
            if (strpos($item['serverName'], 'BroRust') !== false) {
                $projectName = "BroRust";
                $serverName = str_replace('BroRust ', '', $item['serverName']);
            }
            if (strpos($item['serverName'], 'Grand Rust') !== false) {
                $projectName = "Grand Rust";
                $serverName = str_replace('Grand Rust ', '', $item['serverName']);
            }
            if (strpos($item['serverName'], 'BEARZ') !== false) {
                $projectName = "BEARZ";
                $serverName = str_replace('BEARZ', '', $item['serverName']);
            }
            $result[] = $this->serialize([
                                                     'steam_id' => $item['targetSteamID'],
                                                     'reason' => $item['reason'],
                                                     'date' => $item['bandate'],
                                                     'expireDate' => $item['unbanDate'],
                                                     'server' => $serverName,
                                                     'project' => $projectName,
                                                 ]);
        }

        return $result;
    }

    private function serverBans() {
        $key = $this->getKey();
        if (empty($key)) {
            $this->auth();
            $key = $this->getKey();
        }
        $this->_banList = $this->list(0, 50, $key);
    }

    private function serialize($item) {
        $expireDate = $item['expireDate'];
        if (empty($expireDate)) {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime();
            $date->setTimestamp($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        if (strpos(mb_strtolower($item['reason']), 'cheat') !== false || strpos(mb_strtolower($item['reason']), 'чит') !== false) {
            $item['reason'] = "Читы";
        }
        if (strpos(mb_strtolower($item['reason']), 'Bug') !== false
            || strpos(mb_strtolower($item['reason']), 'buguse') !== false
            || strpos(mb_strtolower($item['reason']), 'баг') !== false) {
            $item['reason'] = "Багоюз";
        }
        if (strpos(mb_strtolower($item['reason']), 'результатам') !== false) {
            $item['reason'] = "По результатам проверки";
        }
        if (strpos(mb_strtolower($item['reason']), 'покинул') !== false) {
            $item['reason'] = "Покинул сервер во время проверки";
        }
        if (strpos(mb_strtolower($item['reason']), 'отказ') !== false) {
            $item['reason'] = "Отказ от проверки";
        }
        if (strpos(mb_strtolower($item['reason']), 'реальные деньги') !== false) {
            $item['reason'] = "Продажа за реальные деньги";
        }
        $date = new \DateTime();
        $date->setTimestamp($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }

    private function auth() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://rustcheatcheck.ru/panel/auth.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'login=RustDom&password=vaOtK4OcmRuj4fN&ref=&remember=1');
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getKey() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://rustcheatcheck.ru/panel/banlist");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        if(preg_match_all('|data: { key: \'(.*)\' },|Uis', $response, $result))
        {
            return $result[1][0];
        }

        return null;
    }

    private function getBanList($offset, $count, $key) {
        $ch = curl_init();
        $url = "https://rustcheatcheck.ru/panel/api-banlist.php?draw=1&columns%5B0%5D%5Bdata%5D=targetData.1&columns%5B0%5D%5Bname%5D=&columns%5B0%5D%5Bsearchable%5D=true&columns%5B0%5D%5Borderable%5D=false&columns%5B0%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B0%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B1%5D%5Bdata%5D=targetData.0&columns%5B1%5D%5Bname%5D=&columns%5B1%5D%5Bsearchable%5D=true&columns%5B1%5D%5Borderable%5D=false&columns%5B1%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B1%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B2%5D%5Bdata%5D=targetSteamID&columns%5B2%5D%5Bname%5D=&columns%5B2%5D%5Bsearchable%5D=true&columns%5B2%5D%5Borderable%5D=false&columns%5B2%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B2%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B3%5D%5Bdata%5D=serverName&columns%5B3%5D%5Bname%5D=&columns%5B3%5D%5Bsearchable%5D=true&columns%5B3%5D%5Borderable%5D=false&columns%5B3%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B3%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B4%5D%5Bdata%5D=reason&columns%5B4%5D%5Bname%5D=&columns%5B4%5D%5Bsearchable%5D=true&columns%5B4%5D%5Borderable%5D=false&columns%5B4%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B4%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B5%5D%5Bdata%5D=bandate&columns%5B5%5D%5Bname%5D=&columns%5B5%5D%5Bsearchable%5D=true&columns%5B5%5D%5Borderable%5D=true&columns%5B5%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B5%5D%5Bsearch%5D%5Bregex%5D=false&order%5B0%5D%5Bcolumn%5D=5&order%5B0%5D%5Bdir%5D=desc&start={$offset}&length={$count}&search%5Bvalue%5D=&search%5Bregex%5D=false&key={$key}";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: rustcheatcheck.ru',
            'Referer: https://rustcheatcheck.ru/panel/banlist',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
            'X-Requested-With: XMLHttpRequest',
            'sec-ch-ua: "Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, 1)['aaData'];

        return $result;
    }

}
