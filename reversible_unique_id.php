<?php

class ReversibleUniqueId
{

	const ASCII_CHAR_FIRST	= 48;
	const ASCII_CHAR_LAST	= 126;
	const SHUFFLE_FACTOR	= 16;

	private $_level = 0; //0: exclude reserved; 1: exclude reserved and unsafe

	private $_dictionary = array();

	private $_base = 0;

	private $_offset = 0;

	private $_reservedChars = array(
		58 => 1,
		59 => 1,
		60 => 1,
		61 => 1,
		62 => 1,
		63 => 1,
		64 => 1
	);

	private $_unsafeChars = array(
		91 => 1,
		92 => 1,
		93 => 1,
		94 => 1,
		96 => 1,
		123 => 1,
		124 => 1,
		125 => 1,
		126 => 1
	);

	private $_softAdditionalChars = array(
		45 => 1
	);

	private $_hardAdditionalChars = array(
		33 => 1,
		34 => 1,
		36 => 1,
		38 => 1,
		39 => 1,
		40 => 1,
		41 => 1,
		42 => 1,
		43 => 1,
		45 => 1,
		46 => 1
	);

	private function _printDictionary()
	{
		foreach ($this->_dictionary as $charId)
		{
			echo 'CHAR[' . $charId . ']: ' . chr($charId) . "\n";
		}
	}

	private function _generateDictionary()
	{
		$this->_base = 0;

		for ($i = self::ASCII_CHAR_FIRST; $i <= self::ASCII_CHAR_LAST; $i++)
		{
			$reserved = isset($this->_reservedChars[$i]);
			$unsafe = false;
			if ($this->_level == 1)
			{
				$unsafe = isset($this->_unsafeChars[$i]);
			}

			if (!$reserved && !$unsafe)
			{
				$this->_dictionary[$this->_base] = $i;
				$this->_base++;
			}
		}

		foreach ($this->_softAdditionalChars as $key => $value)
		{
			$this->_dictionary[$this->_base] = $key;
			$this->_base++;
		}

		if ($this->_level == 0)
		{
			foreach ($this->_hardAdditionalChars as $key => $value)
			{
				$this->_dictionary[$this->_base] = $key;
				$this->_base++;
			}
		}

		if (self::SHUFFLE_FACTOR > 1)
		{
			$inverseCounter = $this->_base;
			$counter = 0;
			$shuffleIndex = 0;
			$tempDictionary = array();
			while ($inverseCounter > 0)
			{
				$shuffleIndex = ($shuffleIndex + self::SHUFFLE_FACTOR) % $inverseCounter;
				$tempDictionary[$counter] = $this->_dictionary[$shuffleIndex];
				unset($this->_dictionary[$shuffleIndex]);
				$this->_dictionary = array_values($this->_dictionary);
				$inverseCounter--;
				$counter++;
			}
			$this->_dictionary = $tempDictionary;
		}
		$this->_offset = $this->_base - 1; //This forces to have minimum a length of two.

		return true;
	}

	public function calculateOffsetForMinimumDigits($digits)
	{
		while ($digits > 0)
		{
			$digits--;
			$minimumNumber += pow($this->_base, $digits);
		}

		return $minimumNumber;
	}

	public function encode($number)
	{
		$number += $this->_offset;

		$uId = '';

		do
		{
			$mod = $number % $this->_base;
			$charASCII = intval($number / $this->_base);
			$uId .= chr($this->_dictionary[$mod]);
			$number = $charASCII;
		} while ($charASCII > 0);

		if ($charASCII > 0)
		{
			$uId .= chr($this->_dictionary[$mod]);
		}
		return strrev($uId);
	}

	public function decode($uId)
	{
		$position = strlen($uId);
		$chars = str_split($uId);
		$number = 0;
		foreach ($chars as $char)
		{
			$position--;
			$number += pow($this->_base, $position) * array_search(ord($char), $this->_dictionary);
		}
		$number = $number - $this->_offset;
		return $number;
	}

	public function __construct()
	{
		$this->_generateDictionary();
	}

}

$base = new ReversibleUniqueId();
$uid = $base->encode(12000011);
var_dump($uid);die;
for ($i = 1; $i < 100; $i++)
{
	echo "\n" ;
}
$uid = $base->encode(1);
