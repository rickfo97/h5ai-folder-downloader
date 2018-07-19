<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;


$baseUri = 'BASEURI';
$baseFolder = 'SUBFOLDER';

$guzzle = new Client([
    'base_uri' => $baseUri,
    'verify' => false
]);

$response = $guzzle->get($baseFolder);

$document = new DOMDocument();
$docString = $response->getBody()->getContents();
$document->loadHTML($docString);
$itemList = $document->getElementsByTagName('table')->item(0);
$folder = new Folder(__DIR__ . '/PARENTFOLDER/', $itemList, $guzzle, 'DIRECTORYNAME');

$folder->download($guzzle);


class Folder{
    public $parent;
    public $name;
    public $folders = [];
    public $files = [];

    public function __construct($parent, DOMElement $folder, Client &$guzzle, $name = '')
    {
        $this->parent = $parent;
        $this->name = $name;
        if (!file_exists($this->parent . $this->name . '\\'))
            mkdir($this->parent . $this->name . '\\', umask(0777), true);
        $children = $folder->getElementsByTagName('tr');
        for ($i = 0; $i < $children->length; $i++) {
            $child = $children->item($i);
            if($child->firstChild->tagName == 'th')
                continue;
            $type = $child->firstChild->firstChild->getAttribute('src');
            if(strpos($type, 'file.png')){
                $fileChild = $child->childNodes[1]->firstChild;
                $this->files[] = ['uri' => $fileChild->getAttribute('href'), 'name' => $fileChild->nodeValue];
                continue;
            }
            if(strpos($type, 'folder.png') && !strpos($type, 'folder-parent.png')){
                $folderChild = $child->childNodes[1]->firstChild;
                $uri = $folderChild->getAttribute('href');
                $newFolder = $guzzle->get($uri);
                $document = new DOMDocument();
                $docString = $newFolder->getBody()->getContents();
                $document->loadHTML($docString);
                $this->folders[] = new Folder($this->parent . $this->name . '/', $itemList = $document->getElementsByTagName('table')->item(0),$guzzle, $folderChild->nodeValue);
            }
        }
        echo 'Fetched all uris for: ' . $this->name;
    }

    public function download(Client &$guzzle){
        foreach ($this->files as $file){
            echo 'downloading: ' . $this->parent . $this->name . '/' . $file['name'] . '\n';
            $resource = fopen($this->parent . $this->name . '/' . $file['name'], 'w');
            $guzzle->get($file['uri'], ['sink' => $resource]);
            fclose($resource);
        }
        foreach ($this->folders as $folder){
            $folder->download($guzzle);
        }
    }
}

