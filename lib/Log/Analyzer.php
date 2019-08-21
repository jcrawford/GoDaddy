<?php
namespace GoDaddy\Log;

use BenMorel\ApacheLogParser\Parser;

class Analyzer
{
    protected $file;
    protected $parser;
    protected $totalEntries = 0;

    protected $files = [];
    protected $referrers = [];
    protected $user_agents = [];
    protected $statuses = [];
    protected $errors;
    protected $success;

    public function __construct($filename, $format)
    {
        if(!file_exists($filename)) {
            throw new \Exception('File not found!');
        }

        $this->parser = new Parser($format);
        $this->file = new \SplFileObject($filename, 'r');
    }

    public function getErrorCount()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->success;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getReferrers()
    {
        return $this->referrers;
    }

    public function getUserAgents()
    {
        return $this->user_agents;
    }

    private function iterateLines()
    {
        $count = 0;
        while(!$this->file->eof()) {
            yield $this->file->fgets();
            $this->totalEntries++;
            $count++;
        }
        return $count;
    }

    private function iterate()
    {
        return new \NoRewindIterator($this->iterateLines());
    }

    public function analyze()
    {
        try {
            $lines = $this->iterate();

            foreach($lines as $line) {
                $this->processEntry($this->parser->parse($line, true));
            }

            $this->processResults();
            $this->calculatePercentages();

            return [
                'total_entries' => $this->totalEntries,
                'success' => $this->success,
                'errors' => $this->errors,
                'files' => $this->files,
                'referrers' => $this->referrers,
                'user_agents' => $this->user_agents,
                'statuses' => $this->statuses
            ];
        } catch(\Exception $e) {
            throw $e;
        }
    }
    
    private function processEntry($entry) 
    {
        // Process the request line
        $parts = explode(" ", $entry['firstRequestLine']);
        if(array_key_exists($parts[1], $this->files)) {
            $this->files[$parts[1]]['total']++;
        } else {
            $this->files[$parts[1]]['total'] = 1;
        }

        // process the status
        if(array_key_exists($entry['status'], $this->statuses)) {
            $this->statuses[$entry['status']]['total']++;
        } else {
            $this->statuses[$entry['status']]['total'] = 1;
        }

        // process the referrer
        if(array_key_exists($entry['requestHeader:Referer'], $this->referrers)) {
            $this->referrers[$entry['requestHeader:Referer']]['total']++;
        } else {
            $this->referrers[$entry['requestHeader:Referer']]['total'] = 1;
        }

        // process user agent

        if(array_key_exists($entry['requestHeader:User-Agent'], $this->user_agents)) {
            $this->user_agents[$entry['requestHeader:User-Agent']]['total']++;
        } else {
            $this->user_agents[$entry['requestHeader:User-Agent']]['total'] = 1;
        }
    }

    private function processResults()
    {
        arsort($this->files);
        arsort($this->referrers);
        arsort($this->user_agents);
        
        $this->processStatuses();
        arsort($this->statuses);

        $this->files = array_slice($this->files, 0, 10);
        $this->referrers = array_slice($this->referrers, 0, 10);
        $this->user_agents = array_slice($this->user_agents, 0, 10);
    }

    private function calculatePercentages()
    {
        foreach($this->files as $file => $value) {
            $this->files[$file]['percentage'] = number_format(($this->files[$file]['total'] / $this->totalEntries) * 100, 2) . '%';
        }

        foreach($this->referrers as $referrer => $value) {
            $this->referrers[$referrer]['percentage'] = number_format(($this->referrers[$referrer]['total'] / $this->totalEntries) * 100, 2) . '%';
        }

        foreach($this->user_agents as $user_agent => $value) {
            $this->user_agents[$user_agent]['percentage'] = number_format(($this->user_agents[$user_agent]['total'] / $this->totalEntries) * 100, 2) . '%';
        }
    }

    private function processStatuses()
    {
        $results = [];

        foreach($this->statuses as $code => $value) {
            $key = substr($code, 0, 1) . 'x';
            
            if(!array_key_exists($key, $results)) {
                $results[$key] = [];
            }
            
            if(array_key_exists('total', $results[$key])) {
                $results[$key]['total'] += $value['total'];    
            } else {
                $results[$key]['total'] = $value['total'];
            }
            
        }

        foreach($results as $code => $value) {
            $key = substr($code, 0, 1) . 'x';
            $results[$key]['percentage'] = number_format(($value['total'] / $this->totalEntries) * 100, 2) . '%';
        }

        $this->statuses = $results;
        $this->success = $this->statuses['2x']['total'] + $this->statuses['3x']['total'];
        $this->errors = $this->statuses['4x']['total'] + $this->statuses['5x']['total'];

        arsort($this->statuses);
    }
}