<?php
/**
 * PHP version 5
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace DcGeneral;

abstract class AbstractTranslator implements TranslatorInterface
{
	/**
	 * @param $string
	 *
	 * @param $domain
	 *
	 * @param $locale
	 *
	 * @return mixed
	 */
	abstract protected function getValue($string, $domain, $locale);

	/**
	 * {@inheritdoc}
	 */
	public function translate($string, $domain = null, array $parameters = array(), $locale = null)
	{
		$newString = $this->getValue($string, $domain, $locale);

		if ($newString == $string)
		{
			return $string;
		}

		if (count($parameters)) {
			$newString = vsprintf($newString, $parameters);
		}

		return $newString;
	}

	/**
	 * {@inheritdoc}
	 */
	public function translatePluralized($string, $number, $domain = null, array $parameters = array(), $locale = null)
	{
		$choices = $this->getValue($string, $domain, $locale);

		if (is_array($choices)) {
			if (isset($choices[$number])) {
				$newString = $choices[$number];
			}
			else {
				$array = array();

				foreach ($choices as $range => $choice) {
					$range = explode(':', $range);

					if (count($range) < 2) {
						$range[] = '';
					}

					$array[] = (object) array(
						'range' => (object) array(
								'from' => $range[0],
								'to'   => $range[1],
							),
						'string' => $choice
					);
				}

				for ($i=0; $i<count($array); $i++) {
					$choice = $array[$i];

					// set from number, if not set (notation ":X")
					if (!$choice->range->from) {
						if ($i > 0) {
							$choice->range->from = $array[$i-1]->range->to + 1;
						}
						else {
							$choice->range->from = -PHP_INT_MAX;
						}
					}
					// set to number, if not set (notation "X" or "X:")
					if (!$choice->range->to) {
						if ($i < count($array)-1) {
							$choice->range->to = $array[$i+1]->range->from - 1;
						}
						else {
							$choice->range->to = PHP_INT_MAX;
						}
					}

					if ($number >= $choice->range->from && $number <= $choice->range->to) {
						$newString = $choice->string;
						break;
					}
				}
			}
		}

		if (!isset($newString))
		{
			return $string;
		}

		if (count($parameters)) {
			$newString = vsprintf($newString, $parameters);
		}

		return $newString;
	}
}



