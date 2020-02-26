<?php
/**
 * amoCRM Lite 0.1 (NeuroCRM)
 * User: Evgeny Bogdanov <admin@prow.su>
 * Date: 10.02.2017
 * Time: 9:15
 */

namespace wideweb\brokerBundle\Services;

class Amocrm{
		const AMO_DOMAIN = 'hrscanner.amocrm.ru';
		const AMO_USER = 'Info@hrscanner.ru';
		const AMO_HASH = '65d7df19bdf38a5ab11a2f6c80078630';

		protected static function amo_current_cache($actuality, $data = null) {
			$fp = __DIR__."/current.cache";
			$f = @file($fp);
			if ($actuality) {
				return array((trim(@$f[0]) > (time()-3600)), (isset($f[1]) ? json_decode($f[1], 1) : null));
			} else {
				if (is_array($data)) {
					return file_put_contents($fp, time().PHP_EOL.json_encode($data));
				} else {
					return (isset($f[1]) ? json_decode($f[1], 1) : null);
				}
			}
		}
		protected static function amo_get_current() {
			$data = self::amo_current_cache(true);
			if ($data[0] && is_array($data[1])) {
				return $data[1];
			} else {
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_USERAGENT, 'NeuroCRM Connector/0.1');
				curl_setopt($curl, CURLOPT_URL, "https://" . self::AMO_DOMAIN . '/private/api/v2/json/accounts/current?' . http_build_query(array(
						"USER_LOGIN" => self::AMO_USER,
						"USER_HASH" => self::AMO_HASH
					)));
				curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				$out = curl_exec($curl);
				$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				$code = (int)$code;
				if ($code == 200) {
					$out = json_decode($out,1);
					self::amo_current_cache(false, $out["response"]);
					return $out["response"];
				} else {
					return null;
				}
			}
		}

		public function is_contact_exist($query) {
			$link='https://'.self::AMO_DOMAIN.'/api/v2/contacts'.'?'.http_build_query(array(
					"USER_LOGIN" => self::AMO_USER,
					"USER_HASH" => self::AMO_HASH,
					'query' => $query
				));

			//echo $link; exit;

			$curl=curl_init(); #Сохраняем дескриптор сеанса cURL
			#Устанавливаем необходимые опции для сеанса cURL
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
			curl_setopt($curl,CURLOPT_URL,$link);
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
			// curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
			curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
			curl_setopt($curl,CURLOPT_HEADER,false);
			curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
			curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
			curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
			curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
			$out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
			$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);

			return ! empty($out);
		}

		public function get_random_user() {
			$data = self::amo_get_current();
			$users = array();
			if (isset($data["account"]["users"])) {
				foreach ($data["account"]["users"] as $user) {
					if (!$user["is_admin"] && $user["group_id"] == 0) $users[] = $user["id"];
				}
				if (($size = sizeof($users)) == 0) {
					return 0;
				}
				return $users[mt_rand(0, $size-1)];
			} else {
				return 0;
			}
			//print_r($data);
		}

		public static function get_incremental_user() {
			$data = self::amo_get_current();
			$users = array();
			//file_put_contents(__DIR__.'/users.inc',print_r($data["account"]["users"],1));//when need to know users fields
			$fp = __DIR__.'/next.inc';
			$f = @file_get_contents($fp);
			if (isset($data["account"]["users"])) {
				foreach ($data["account"]["users"] as $user) {
					if (!$user["is_admin"] && $user["group_id"] == 0) $users[] = $user["id"];
				}
				//print_r($users);
				$id = array_search($f, $users);
				if ($id === false) {
					$id = 0;
				} else {
					$id++;
				}
				if ($id >= sizeof($users)) {
					$id = 0;
				}
				file_put_contents($fp, $users[$id]);
				return (!empty($users) ? $users[$id] : 0);
			} else {
				return 0;
			}
		}

		public static function get_manager_name($id) {
			$data = self::amo_get_current();
			if (isset($data["account"]["users"])) {
				foreach($data["account"]["users"] as $user) {
					if($user['id'] == $id) {
						return $user['name'];
					}
				}
			} else {
				return 0;
			}
		}

		public static function add_contact ($data) {
		    return self::exec('/contacts',$data);
		}

		public static function update_contact ($data) {
        		    return self::exec('/contacts',$data);
        		}

        public static function add_lead ($data) {
            return self::exec('/leads',$data);
     	}

     	public static function add_task ($data) {
            return self::exec('/tasks',$data);
       	}

       	public static function add_note ($data) {
            return self::exec('/notes',$data);
       	}



        private function exec ($url, $data) {

            //$link = '/accounts/current';//example

            $link='https://'.self::AMO_DOMAIN.'/api/v2'.$url.'?'.http_build_query(array(
                                                             						"USER_LOGIN" => self::AMO_USER,
                                                             						"USER_HASH" => self::AMO_HASH
                                                             					));

            //echo $link; exit;

            $curl=curl_init(); #Сохраняем дескриптор сеанса cURL
            #Устанавливаем необходимые опции для сеанса cURL
            curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
            curl_setopt($curl,CURLOPT_URL,$link);
            curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
            curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
            curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
            curl_setopt($curl,CURLOPT_HEADER,false);
            curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
            curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
            $out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
            $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);

            $code = (int)$code;
            if ($code == 200) {
                $out = json_decode($out,1);
                //self::amo_current_cache(false, $out["response"]);
                return $out/*["response"]*/;
            } else {
                return $out;
            }

            return $resp;
        }
}
