<?php
namespace App\Interfaces;

interface AiInterface
{
    public function generateContent(string $sting): string;

    public function textJsonToArray(string $jsonText);
}