<?php
/**
 * Reversible Unique ID Class
 *
 * @license http://www.freebsd.org/copyright/freebsd-license.html FreeBSD Licence
 * @author roberto@nygaard.es - Roberto Nygaard - @topikito - github.com/topikito
 * @version 0.1 - Experimental
 *
 * @example
 *	$RUId = new ReversibleUniqueId();
 *  $encoded = $RUId->encode(<number>);
 *  $decoded = $RUId->decode(<encoded string>);
 */
class ReversibleUniqueId
{
	/**
	 * ASCII limits
	 */
	const ASCII_CHAR_FIRST	= 48;	//First safe ASCII character: 0
	const ASCII_CHAR_LAST	= 122;	//Last safe ASCII character: z

	/**
	 * For preventing secuential order in the UId strings, we have
	 * the option to shuffle the dictionary. Works if higher than 1
	 */
	const SHUFFLE_FACTOR	= 23;

	/**
	 * Specifies the level of the generated string.
	 *	0:	This creates a string with all the 'accepted' chars that an
	 *		URL can read. It only exclude reserved chars and it adds
	 *		what I call "soft" chars and "hard" ones. This generates
	 *		a dictionary with 90 elements: Base 90.
	 *
	 *	1:	This creates a string with all the safe chars that an URL
	 *		accepts. It excludes reserved and unsafe characters and adds
	 *		"soft" characteres to the dictionary. This mode generates
	 *		a Base 64 dictionary.
	 *
	 * @var int
	 */
	private $_level = 0;

	/**
	 * The array where we will store the dictionary in order to encode/decode
	 * @var array
	 */
	private $_dictionary = array();
	private $_reverseDictionary = array();

	/**
	 * Size of the dictionary
	 * @var int
	 */
	private $_base = 0;

	/**
	 * Margin to start from if we don't wan't to begin with the first entry.
	 * Useful if we want to generate a UId string with minimum 2 chars.
	 * @var int
	 */
	private $_offset = 0;

	/**
	 * Chars that cannot be read correctly by a browser because they are
	 * reserved and have their own behaviour.
	 * @var array
	 */
	private $_reservedChars = array(
		35 => 1,	//Hash 			-- Not included by default
		37 => 1,	//Percent  		-- Not included by default
		47 => 1,	//Slash 			-- Not included by default
		63 => 1,	//Question mark
		92 => 1		//Inverted slash
	);

	/**
	 * Chars that may not be safe because they may need a conversion to
	 * html entity.
	 * @var array
	 */
	private $_unsafeChars = array(
		58 => 1,
		59 => 1,
		60 => 1,
		61 => 1,
		62 => 1,
		64 => 1,
		91 => 1,
		92 => 1,
		93 => 1,
		94 => 1,
		96 => 1
	);

	/**
	 * Safe chars that are allowed but not in the alfa-numeric group.
	 * @var array
	 */
	private $_softAdditionalChars = array(
		45 => 1
	);

	/**
	 * Chars that may not be safe and are not included in the alfa-numeric group.
	 * @var array
	 */
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
		44 => 1,
		45 => 1,
		46 => 1,
		123 => 1,
		124 => 1,
		125 => 1,
		126 => 1
	);

	/**
	 * Builds the dictionary.
	 * @return boolean
	 */
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

		$this->_reverseDictionary = array_flip($this->_dictionary);

		return true;
	}

	/**
	 * Returns the minimum number to generate a $digits size string
	 * @param int $digits
	 * @return int
	 */
	public function calculateOffsetForMinimumDigits($digits)
	{
		$minimumNumber = 0;
		$digits--;
		while ($digits > 0)
		{
			$minimumNumber += pow($this->_base, $digits);
			$digits--;
		}

		return $minimumNumber - 1;
	}

	/**
	 * Sets the offset so the minimum size of the UId equals $digits
	 * @param int $digits
	 * @return \ReversibleUniqueId
	 */
	public function setMinimumDigits($digits)
	{
		$this->_offset = $this->calculateOffsetForMinimumDigits($digits);
		return $this;
	}

	/**
	 * Shows the dictionary
	 * @param bool $charOrdered
	 */
	public function printDictionary($charOrdered = false)
	{
		$copy = $this->_dictionary;
		if ($charOrdered)
		{
			asort($copy);
		}
		foreach ($copy as $number => $charId)
		{
			echo 'NUMBER ' . $number . ' => CHAR[' . $charId . ']: ' . chr($charId) . "\n";
		}
	}

	/**
	 * Returns the UId for the $number given.
	 * @param int $number
	 * @return string
	 */
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

		return strrev($uId);
	}

	/**
	 * Returns the number corresponding to the $uId string
	 * @param string $uId
	 * @return int
	 */
	public function decode($uId)
	{
		$position = strlen($uId);
		$chars = str_split($uId);
		$number = 0;
		foreach ($chars as $char)
		{
			$position--;
			$number += pow($this->_base, $position) * $this->_reverseDictionary[ord($char)];
		}
		return ($number - $this->_offset);
	}

	/**
	 * Magic Method constructor
	 */
	public function __construct()
	{
		$this->_generateDictionary();
		$this->setMinimumDigits(2);
	}
}