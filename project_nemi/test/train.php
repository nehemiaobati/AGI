<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
use NlpTools\Stemmers\PorterStemmer;
use NlpTools\Utils\StopWords;
use NlpTools\FeatureFactories\DataAsFeatures;
use NlpTools\Classifiers\MultinomialNBClassifier;
use NlpTools\Models\FeatureBasedNB;
use NlpTools\Documents\TrainingSet;
use NlpTools\Documents\TokensDocument;

// --- 1. Preprocessing Function (mirrors MemoryManager::extractEntities) ---
function preprocessText(string $text): array
{
    // 1. Sanitize and Normalize Text
    $text = strtolower($text);
    $text = preg_replace('/https?:\/\/[^\s]+/', ' ', $text); // Keep URL removal

    // 2. Tokenize the text using the library's robust tokenizer
    $tokenizer = new WhitespaceAndPunctuationTokenizer();
    $tokens = $tokenizer->tokenize($text);

    // 3. Filter out stop words using the list from config.php
    $stopWords = new StopWords(NLP_STOP_WORDS);
    $filteredTokens = [];
    foreach ($tokens as $token) {
        $transformedToken = $stopWords->transform($token);
        if ($transformedToken !== null) {
            $filteredTokens[] = $transformedToken;
        }
    }

    // 4. Reduce words to their root form (stemming)
    $stemmer = new PorterStemmer();
    $stemmedTokens = array_map([$stemmer, 'stem'], $filteredTokens);

    // 5. Final cleanup and return unique entities
    return array_filter(array_unique($stemmedTokens), fn($word) => strlen($word) > 2);
}

// --- 2. Load Training Data ---
$trainingDataFile = __DIR__ . '/training_data.csv';
$trainingData = [];

if (($handle = fopen($trainingDataFile, 'r')) !== FALSE) {
    $header = fgetcsv($handle); // Skip header row
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) == 2) {
            $trainingData[] = ['text' => $row[0], 'label' => $row[1]];
        }
    }
    fclose($handle);
} else {
    die("Error: Could not open training_data.csv\n");
}

if (empty($trainingData)) {
    die("Error: No training data found in training_data.csv\n");
}



// --- 3. Prepare Training Set for NLP-Tools ---
$trainingSet = new TrainingSet();
$labels = [];

foreach ($trainingData as $item) {
    $processedTokens = preprocessText($item['text']);
    $trainingSet->addDocument($item['label'], new TokensDocument($processedTokens));
    $labels[] = $item['label'];
}

// --- 4. Feature Generation (TF-IDF) ---
$featureFactory = new DataAsFeatures();

// --- 5. Classifier Training (Multinomial Naive Bayes) ---
$model = new FeatureBasedNB();

$model->train($featureFactory, $trainingSet);


$classifier = new MultinomialNBClassifier($featureFactory, $model);


// --- 6. Serialize and Save Models ---
$tfIdfModelFile = __DIR__ . '/tf_idf.model';
$classifierModelFile = __DIR__ . '/classifier.model';

file_put_contents($tfIdfModelFile, serialize($featureFactory));
file_put_contents($classifierModelFile, serialize($classifier));



?>