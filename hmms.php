<?php
class Markov{
    var $A;
    var $B;
    var $pi;
    var $states;
    var $labels;
    var $path;
    var $un_labeled;
    function __construct(){
        header('Content-Type: text/html; charset=utf-8');
        $this->load();
    }

    function viterbi($oString){
        $this->o=$oString;
        $oString=$this->sanitize($oString);
        $observations=explode(' ',$oString);
        $this->obs=$observations;
        $totalStates=count($this->states);
        $delta=array();
        foreach($this->states as $i=>$s){
            $delta[0][$s]=$this->pi($s)*$this->B($s,$observations[0]);
        }

        foreach($observations as $i=>$o){
            if($i==0){
                continue;
            }
            foreach($this->states as $j=>$s){
                $delta[$i][$s]=$this->delta($delta,$i,$s,$o);
            }
        }
        return $delta;
    }
    function tag($string){
        $delta=$this->viterbi($string);
        return $this->_tag($delta);
    }
    function _tag($delta=array()){
        if($delta){
            $this->findPath($delta);
        }
        $tagged='';
        foreach($this->obs as $i=>$o){
            $label=key($this->path[$i]);
            if(is_numeric($label)){
                $label='';
            }else{
                $label='/'.$label;
            }
            $tagged.=$o.$label.' ';
        }
        return trim($tagged);
    }

    function delta($delta,$i,$s2,$o){
        $values=array();
        foreach($this->states as $s1){
            $values[]=$delta[$i-1][$s1]*$this->A($s1,$s2);
        }
        $max_value=max($values);
        return $max_value*$this->B($s2,$o);
    }
    function A($i,$j){
        if(isset($this->A[$i][$j])){
            return $this->A[$i][$j];
        }
        return 0.0000001;
    }
    function B($i,$j){
        if(isset($this->B[$i][$j])){
            return $this->B[$i][$j];
        }
        return 0.0000001;
    }
    function pi($i){
        if(isset($this->pi[$i])){
            return $this->pi[$i];
        }
        return 0.0000001;
    }
    function findPath($delta){

        foreach($delta as $array){
            arsort ($array);
            //print_r($array);
            foreach($array as $label => $value){
                $this->path[][$label]=$value;break;
            }
        }
        return $this->path;
    }
    function sanitize($o){
        $o=str_replace(', ',' ',$o);
        //$o=str_replace('-',' ',$o);
        $o=str_replace('. ',' ',$o);
        $o=str_replace('(',' ',$o);
        $o=str_replace(')',' ',$o);
        $o=str_replace('/',' ',$o);
        $o=mb_strtolower($o,'UTF8');
        while(strpos($o,'  ')!==false)
            $o=str_replace('  ',' ',$o);
        $o=trim($o);
        return $o;
    }
    function load($retrain=false){

        if(!$this->loadModel()||$retrain){
            $this->train();
            $this->save();
            $this->loadModel();
        }
    }
    function loadModel(){//Load model parameter
        if(file_exists('a.php')&&file_exists('b.php')&&file_exists('pi.php')&&file_exists('l.php')&&file_exists('s.php')){
            $this->A=include('a.php');
            $this->B=include('b.php');
            $this->pi=include('pi.php');
            $this->states=array_keys(include('s.php'));
            $this->labels=array_keys(include('l.php'));
            if(!is_array($this->A)||!is_array($this->B)||!is_array($this->pi)||!is_array($this->states)||!is_array($this->labels))
                return false;
            return true;
        }
        return false;
    }
    function train($file='train.txt'){
        $this->train_file=$file;
        new MarkovTrain($this);
    }
    function save(){
        $A='<?php return '.var_export($this->A,true).';';
        $B='<?php return '.var_export($this->B,true).';';
        $pi='<?php return '.var_export($this->pi,true).';';
        $states='<?php return '.var_export($this->states,true).';';
        $labels='<?php return '.var_export($this->labels,true).';';
        $un_labeled='<?php return '.var_export($this->un_labeled,true).';';
        file_put_contents('a.php',$A);
        file_put_contents('b.php',$B);
        file_put_contents('pi.php',$pi);
        file_put_contents('s.php',$states);
        file_put_contents('l.php',$labels);
        file_put_contents('ul.php',$un_labeled);
    }
}
class MarkovTrain{
    var $markovModel;
    var $un_labeled=array();
    var $labels=array();
    function __construct($markov=null){
        $this->init($markov);
        $this->train();
    }
    function init($markov=null){
        if($markov){
            $this->markovModel=$markov;
        }
    }
    function getData($limit=38){
        $train_data=file_get_contents($this->markovModel->train_file);
        $train_array=explode("\n",$train_data);
        $train_array_data=array();
        foreach($train_array as $line){
            if(!strpos($line,'/'))
                break;
            $train_array_data[]=$line;
        }
        //$train_array=array_slice($train_array,0,$limit);
        return $train_array_data;
    }
    function train(){
        $data=$this->getData();
        $wordsCount=0;
        $labelCount=array();
        $labelTrans=array();
        $wordsPerLabel=array();
        foreach($data as $line){
            $this->parse_line($line,$wordsCount,$labelCount,$labelTrans,$wordsPerLabel);
        }
        $this->calculateProb($wordsCount,$labelCount,$labelTrans,$wordsPerLabel);
        $this->markovModel->states=$this->states;
        $this->markovModel->labels=$this->labels;
        $this->markovModel->un_labeled=$this->un_labeled;
    }
    function calculateProb($wordsCount,$labelCount,$labelTrans,$wordsPerLabel){
        $A=array();
        $B=array();
        $pi=$this->calciProb($labelCount);
        foreach($labelTrans as $label=>$stat){
            $A[$label]=$this->calciProb($stat);
        }
        foreach($wordsPerLabel as $label=>$stat){
            $B[$label]=$this->calciProb($stat);
        }
        $this->markovModel->A=$A;
        $this->markovModel->B=$B;
        $this->markovModel->pi=$pi;

    }
    function calciProb($static){
        $totalLabelCount=0;
        $result=array();
        foreach($static as $count){
            $totalLabelCount+=$count;
        }
        foreach($static as $label=>$count){
            $result[$label]=$count/$totalLabelCount;
        }
        return $result;
    }
    function parse_line($line,&$wordsCount,&$labelCount,&$labelTrans,&$wordsPerLabel){
        $line=trim($line);
        $words=explode(' ',$line);
        $wordsCount+=count($words);
        $prevLabel=null;
        foreach($words as $word){
            list($word_text,$word_label)=$this->parse_word($word);
            if(!isset($wordsPerLabel[$word_label][$word_text])){
                $wordsPerLabel[$word_label][$word_text]=1;
            }else{
                $wordsPerLabel[$word_label][$word_text]++;
            }
            if(!isset($labelCount[$word_label])){
                $labelCount[$word_label]=1;
            }else{
                $labelCount[$word_label]++;
            }
            if($prevLabel){
                $transLabel=$prevLabel.'->'.$word_label;
                if(!isset($labelTrans[$prevLabel][$word_label])){
                    $labelTrans[$prevLabel][$word_label]=1;
                }else{
                    $labelTrans[$prevLabel][$word_label]++;
                }
            }
            $prevLabel=$word_label;
        }
    }
    function parse_word($word){
        $word_arr=explode('/',$word);
        $word_text=$word_arr[0];
        if(isset($word_arr[1])){
            $word_label=$word_arr[1];
            $this->labels[$word_label]=1;
        }else{
            if(false===($word_label=array_search($word_text,$this->un_labeled))){
                $this->un_labeled[]=$word_text;
                $word_label=count($this->un_labeled)-1;
            }

        }
        $this->states[$word_label]=1;
        return array($word_text,$word_label);
    }
}
