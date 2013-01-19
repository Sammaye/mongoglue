<?php
namespace mongoglue\helpers;

/**
 * Crypt helper
 * 
 * Provides a basis to perform secure hashing/encrypting and decrypting of strings
 * 
 * The blowfish code comes from: http://stackoverflow.com/questions/4795385/how-do-you-use-bcrypt-for-hashing-passwords-in-php 
 * 
 */
class Crypt{

	public $rounds;
	public $mode;

	private $randomState;

	static function AES_encrypt256($blurb){
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	    $key = "S4M__1-L-2_-+M6N__00c=++./..#+";
	    return mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $blurb, MCRYPT_MODE_ECB, $iv);
	}

	static function AES_decrypt256($blurb){
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	    $key = "S4M__1-L-2_-+M6N__00c=++./..#+";
	    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $blurb, MCRYPT_MODE_ECB, $iv));
	}

	static function blowfish_hash($input, $rounds = 15){
		$o = new GCrypt();
		$o->mode = 'blowfish';
		$o->rounds = $rounds;
		return $o->hash($input);
	}

	static function verify($input, $existingHash) {
		$hash = crypt($input, $existingHash);
		return $hash === $existingHash;
	}

	public function hash($input) {
		$hash = crypt($input, $this->getSalt());
		if(strlen($hash) > 13)
			return $hash;
		return false;
	}

	private function getSalt() {
		if($this->mode == 'sha512'){
			$salt = sprintf('$6$rounds=%02d$', $this->rounds);
		}else{
			$salt = sprintf('$2a$%02d$', $this->rounds);
		}

		$bytes = $this->getRandomBytes(16);
		$salt .= $this->encodeBytes($bytes);
		return $salt;
	}

	private function getRandomBytes($count) {
		$bytes = '';

		if(function_exists('openssl_random_pseudo_bytes') &&
		(strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) { // OpenSSL slow on Win
			$bytes = openssl_random_pseudo_bytes($count);
		}

		if($bytes === '' && is_readable('/dev/urandom') &&
		($hRand = @fopen('/dev/urandom', 'rb')) !== FALSE) {
			$bytes = fread($hRand, $count);
			fclose($hRand);
		}

		if(strlen($bytes) < $count) {
			$bytes = '';

			if($this->randomState === null) {
				$this->randomState = microtime();
				if(function_exists('getmypid')) {
					$this->randomState .= getmypid();
				}
			}

			for($i = 0; $i < $count; $i += 16) {
				$this->randomState = md5(microtime() . $this->randomState);

				if (PHP_VERSION >= '5') {
					$bytes .= md5($this->randomState, true);
				} else {
					$bytes .= pack('H*', md5($this->randomState));
				}
			}

			$bytes = substr($bytes, 0, $count);
		}

		return $bytes;
	}

	private function encodeBytes($input) {
		// The following is code from the PHP Password Hashing Framework
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		$output = '';
		$i = 0;
		do {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= 16) {
				$output .= $itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			$output .= $itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}
}