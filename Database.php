<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private $skipValue;


    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {

        //Выделить от запроса условный блок
        preg_match('/^([^{]*)(?:{([^}]*)})?/',  $query, $match);

        $query = $match[1] ?? '';
        $block = $match[2] ?? '';

        //Выполнить замены спецификаторов на значения
        foreach ($args as $key => $arg) {

            //Замена по одному найденному спецификатору в рамках одной итерации
            //Обработка тела запроса без условного блока
            if(preg_match('/\?/', $query)) {
                $query = preg_replace_callback(
                    '/\?[dfa#]?/', function($match) use ($arg) {
                        return match($match[0]) {
                            '?d' => (int)$this->getValue($arg),
                            '?f' => (float)$this->getValue($arg),
                            '?a' => $this->getValue($arg),
                            '?#' => $this->getValue($arg, false),
                            '?'  => $this->getValue($arg, true),
                        };
                    }, $query, 1);

                continue;
            }

            //Обработка условного блока
            if(preg_match('/\?/', $block)) {

                //Замена спецификатора в условном блоке
                //Если $arg = null, то условный блок не выводится иначе значение преобразуется из $arg
                if(is_null($arg)) {
                    $this->skipValue = null;
                    $block = '';
                } else
                    $block = preg_replace('/\?[dfa#]?/', $this->getValue($arg), $block, 1);

                continue;
            }

        }
        
        return $query.$block;
        //throw new Exception();
    }

    public function skip()
    {
        return $this->skipValue;
        //throw new Exception();
    }

    /**
     * Преобразование значения аргумента в необходимый формат
     * 
     * @param mixed $argument значение аргумента
     * @param bool $qFormat формат кавычек для значения string типа
     */
    private function getValue($argument, $qFormat=false) {
        
        $result = '';

        do {
            //Значение аргумента не массив
            if(!is_array($argument)) {
                $result = $this->checkType($argument, $qFormat);
                break;
            }

            //Значение аргумента одномерный массив
            if(in_array(0, array_keys((array)$argument))) {
                $result = implode(', ', array_map(function($item) { 
                    return $this->checkType($item);
                }, (array)$argument));
                break;
            }

            //Значение аргумента ассоциативный массив
            if(is_array($argument)) {
                $list = [];
                foreach ((array)$argument as $key => $item) {
                    $key = (!empty($key)) ? $this->checkType($key) : 'NULL';
                    $list[] = $key . ' = ' .  $this->checkType($item, true);
                }
                $result = implode(', ',(array)$list);
                break;
            }

            throw new Exception('Неверный тип аргумента');

        } while(0);

        return $result;
    }

    /**
     * Проверка типа элемента
     * 
     * @param mixed $item значение элемента
     * @param bool $qFormat формат кавычек для значения string типа 
     */
    private function checkType($item, $qFormat = false) {

        $result = '';

        do {

            if(is_null($item)) {
                $result = 'NULL';
                break;
            }

            if(is_bool($item)) {
                $result = (int)$item;
                break;
            }

            if(is_int($item)) {
                $result = (int)$item;
                break;
            }

            if(is_float($item)) {
                $result = (float)$item;
                break;
            }
            
            if(is_string($item) && $qFormat) {
                $result = "'".$this->mysqli->real_escape_string((string)$item)."'";
                break;
            }

            if(is_string($item) && !$qFormat) {
                $result = '`'.$this->mysqli->real_escape_string((string)$item).'`';
                break;
            }

            throw new Exception('Неверный тип переменной');

        } while(0);

        return $result;

    }

}
