<?php

/**
 * ------------------------------
 *   TEXY! DEFAULT POST MODULES
 * ------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Modules for parsing parts of text 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */



// security - include texy.php, not this file
if (!defined('TEXY')) die();





/**
 * AUTOMATIC REPLACEMENTS inline module
 * ------------------------------------
 *
 */
class TexyQuickCorrectModule extends TexyModule {


  function postProcess() {
    $replace = array(
         '#(?<!&quot;)&quot;(?!\ |&quot;)(.+)(?<!\ |&quot;)&quot;(?!&quot;)()#U' 
                                                              => '&bdquo;$1&ldquo;',          // double ""
         '#(?<!&\#039;)&\#039;(?!\ |&\#039;)(.+)(?<!\ |&\#039;)&\#039;(?!&\#039;)()#U' 
                                                              => '&sbquo;$1&lsquo;',          // single ''
         '#(\S ?)\.{3}#'                                      => '$1&#8230;',                 // ellipsis  
         '#(\ |\d|\.)-(\ |\d)#'                               => '$1&ndash;$2',               // en dash    
         '#(\d+) ?x ?(\d+)#'                                  => '$1&#215;$2',                // dimension sign
         '#(\S ?)\(TM\)|\[TM\]#i'                             => '$1&trade;',                 // trademark  
         '#(\S ?)\(R\)|\[R\]#i'                               => '$1&reg;',                   // registered
         '#(\S ?)\(C\)|\[C\]#i'                               => '$1&copy;',                  // copyright 
         '#(\d{3}) (\d{3}) (\d{3}) (\d{3})#'                  => '$1&nbsp;$2&nbsp;$3&nbsp;$4',// phone number 123 123 123 123
         '#(\d{3}) (\d{3}) (\d{3})#'                          => '$1&nbsp;$2&nbsp;$3',        // phone number 123 123 123
         '#(?<=^| |\t)'.TEXY_HASHEX.'([ksvzKSVZOoUuIiA])'.TEXY_HASHEX
         .' +'.TEXY_HASHEX.'(['.TEXY_CHAR.'])#m'.TEXY_PATTERN_UTF  => '$1$2$3&nbsp;$4$5',          // space after preposition
    );

    $this->texy->text = preg_replace(array_keys($replace), array_values($replace), $this->texy->text);
  }



} // TexyQuickCorrectModule







/**
 * LONG WORDS WRAP inline module
 * -----------------------------
 *
 */
class TexyLongWordsModule extends TexyModule {
  var $wordLimit = 20;
  var $shy;

  function postProcess() {
    $text = & $this->texy->text;
    $this->shy = TEXY_UTF8 ? "\xC2\xAD" : "\xAD";
    $this->nbsp = TEXY_UTF8 ? "\xC2\xA0" : "\xA0";
    $text = strtr($text, array('&shy;'  => $this->shy, '&nbsp;' => $this->nbsp));
    $text = preg_replace_callback('#[^\ \n\t\-\xAD'.TEXY_HASHSPACE.']{'.$this->wordLimit.',}#'.TEXY_PATTERN_UTF, array(&$this, 'replace'), $text);
    $text = strtr($text, array($this->shy => '&shy;', $this->nbsp => '&nbsp;'));
  }


  // rozd�l� dlouh� slova na slabiky - EXPERIMENT�LN�
  // (c) David Grudl, Ernesto De Spirito
  //
  function replace(&$matches) {
    list($mWord) = $matches;
    //    [0] => lllloooonnnnggggwwwoorrdddd

    $chars = array();
    preg_match_all('#&\\#?[a-z0-9]+;|['.TEXY_HASH.']+|.#'.TEXY_PATTERN_UTF, $mWord, $chars);
    $chars = $chars[0];
    if (count($chars) < $this->wordLimit) return $mWord;
    
    $consonants = array_flip(array(
                        'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','y','z',
                        'B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z',
                        '�','�','�','�','�','�','�','�',            //czech windows-1250
                        '�','�','�','�','�','�','�','�',
                        'č','ď','ň','ř','š','ť','ý','ž',    //czech utf-8
                        'Č','Ď','Ň','Ř','Š','Ť','Ý','Ž'));
    $vowels     = array_flip(array(
                        'a','e','i','o','y','u',
                        'A','E','I','O','Y','U',
                        '�','�','�','�','�','�','�','�',            //czech windows-1250
                        '�','�','�','�','�','�','�','�',
                        'á','é','ě','í','ó','ý','ú','ů',    //czech utf-8
                        'Á','É','Ě','Í','Ó','Ý','Ú','Ů'));

    $before_r   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',
                        '�','�','�','�','�','�','�','�',                  //czech windows-1250
                        'č','Č','ď','Ď','�t','�_','ť','Ť',          //czech utf-8
                        ));

    $before_l   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',
                        '�','�','�','�','�','�',                          //czech windows-1250
                        'č','Č','ď','Ď','ť','Ť',                    //czech utf-8
                        ));

    $before_h   = array_flip(array('c', 'C', 's', 'S'));

    $doubleVowels = array_flip(array('a', 'A','o', 'O'));

    // consts
    $DONT = 0;   // don't hyphenate
    $HERE = 1;   // hyphenate here
    $AFTER = 2;  // hyphenate after

    $s = array(); 
    $trans = array();

    $s[] = '';    
    $trans[] = -1;
    $hashCounter = $len = $counter = 0;
    foreach ($chars as $key => $char) {
      if (ord($char{0}) < 32) continue;
      $s[] = $char;
      $trans[] = $key;
    }
    $s[] = '';
    $len = count($s) - 2;

    $positions = array();
    $a = 1; $last = 1;
    
    while ($a < $len) {
      $hyphen = $DONT; // Do not hyphenate
      do {
        if ($s[$a] == '.') { $hyphen = $HERE; break; }   // ???

        if (isset($consonants[$s[$a]])) {  // souhl�sky

          if (isset($vowels[$s[$a+1]])) {
            if (isset($vowels[$s[$a-1]])) $hyphen = $HERE;
            break;
          }

          if (($s[$a] == 's') && ($s[$a-1] == 'n') && isset($consonants[$s[$a+1]])) { $hyphen = $AFTER; break; }

          if (isset($consonants[$s[$a+1]]) && isset($vowels[$s[$a-1]])) {
            if ($s[$a+1] == 'r') {
              $hyphen = isset($before_r[$s[$a]]) ? $HERE : $AFTER;
              break;
            }

            if ($s[$a+1] == 'l') {
              $hyphen = isset($before_l[$s[$a]]) ? $HERE : $AFTER;
              break;
            }

            if ($s[$a+1] == 'h') { // CH
              $hyphen = isset($before_h[$s[$a]]) ? $DONT : $AFTER;
              break;
            }

            $hyphen = $AFTER;
            break;
          }

          break;
        }   // konec souhlasky


        if (($s[$a] == 'u') && isset($doubleVowels[$s[$a-1]])) { $hyphen = $AFTER; break; }
        if (in_array($s[$a], $vowels) && isset($vowels[$s[$a-1]])) { $hyphen = $HERE; break; }

      } while(0); 

      if ($hyphen == $DONT && ($a - $last > $this->wordLimit*0.6)) $positions[] = $last = $a-1; // Hyphenate here
      if ($hyphen == $HERE) $positions[] = $last = $a-1; // Hyphenate here
      if ($hyphen == $AFTER) { $positions[] = $last = $a; $a++; } // Hyphenate after

      $a++;
    } // while


    $a = end($positions);
    if (($a == $len-1) && isset($consonants[$s[$len]])) 
      array_pop($positions);


    $syllables = array();
    $last = 0;   
    foreach ($positions as $pos) {
      if ($pos - $last > $this->wordLimit*0.6) {
        $syllables[] = implode('', array_splice($chars, 0, $trans[$pos] - $trans[$last]));
        $last = $pos;
      }
    }
    $syllables[] = implode('', $chars);

    $text = implode($this->shy, $syllables);
    $text = strtr($text, array($this->shy.$this->nbsp => ' ', $this->nbsp.$this->shy => ' '));

    return $text;
  }



} // TexyLongWordsModule





?>