<?php
// Automatyczne formatowanie VS Code (bez użycia AI)

//zad 2
// W programie kalkulacyjnym, np. Excel, mamy do czynienia z wierszami i kolumnami. Wiersze
// przyjmują wartość numeryczną, od 1, kolumny z kolei oznaczone są literami, zaczynając od A. Napisz
// program w języku PHP, który umożliwi otrzymanie informacji o kolumnie w postaci numerycznej.
// Przykładowo: komórka A2 powinna otrzymać wartość 1.2, komórka B2 wartość 2.2, komórka A500
// wartość 1.500.

function konwertujKomorke($adres)
{
    // regex do wyciagniecia kolumny i wiersza
    // rozbija na czesc z literami i liczbami
    preg_match('/([A-Z]+)([0-9]+)/', $adres, $matches);
    $kolumna = $matches[1];
    $wiersz = $matches[2];

    // konwersja kolumny na liczbe
    // https://www.php.net/manual/en/function.ord.php
    // ord — Convert the first byte of a string to a value between 0 and 255
    $numerKolumny = 0;
    $podstawa = ord('Z') - ord('A') + 1; // ile jest liter od A-Z, +1 bo liczmy od 0
    for ($i = 0; $i < strlen($kolumna); $i++) {
        $numerKolumny = $numerKolumny * $podstawa + (ord($kolumna[$i]) - ord('A') + 1);
    }

    return $numerKolumny . '.' . $wiersz;
}
//////////////////////////////////////////////////////////////////////////////////////
//test w konsoli: php zadanie2.php
echo "A2 -> " . konwertujKomorke('A2') . PHP_EOL;
echo "B2 -> " . konwertujKomorke('B2') . PHP_EOL;
echo "A500 -> " . konwertujKomorke('A500') . PHP_EOL;
//////////////////////////////////////////////////////////////////////////////////////
//test w html: php zadanie2.php > test.html | firefox test.html
// echo "<br>Testy krotkiej wersji:<br>";
// echo "A2 -> " . konwertujKomorke('A2') . "<br>";
// echo "B2 -> " . konwertujKomorke('B2') . "<br>";
// echo "A500 -> " . konwertujKomorke('A500') . "<br>";
