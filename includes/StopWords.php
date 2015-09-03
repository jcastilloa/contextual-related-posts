<?php
/**
 * StopWords.
 *
 * Devuelve un array con los stop words, o los elimina de un texto.
 *
 */

class StopWords {

    private $preposiciones = array(
        'a',
        'ante',
        'bajo',
        'cabe',
        'con',
        'contra',
        'de',
        'desde',
        'durante',
        'en',
        'entre',
        'hacia',
        'hasta',
        'mediante',
        'para',
        'por',
        'según',
        'sin',
        'so',
        'sobre',
        'cabe',
        'tras'
    );

    private $articulos = array(

        'el',
        'la',
        'los',
        'las',
        'unos',
        'unas',
        'este',
        'esto',
        'estos',
        'esos',
        'aquel',
        'aquellos',
        'esta',
        'estas',
        'esa',
        'esas',
        'aquella',
        'aquellas',
        'éste',
        'ésta',
        'éstos',
        'éstas',
        'aquél',
        'aquéllos',
        'aquéllas'

    );

    private $varios = array(
        'euro',
        'euros',
        '€',
        'año',
        'años'
    );

    private $stop_words;

    function __construct()
    {
        $this->stop_words = array_merge($this->preposiciones, $this->articulos, $this->varios);
    }

    public function getStopWords()
    {
        return $this->stop_words;
    }

    public function stripStopWords($text)
    {
        //strip numbers
        $text = preg_replace('/[0-9]+/', '', $text);

        $text_array = explode(" ", $text);

        //strip stop words
        $filtered_array = array_diff($text_array, $this->stop_words);

        return implode(' ', $filtered_array);
    }

}