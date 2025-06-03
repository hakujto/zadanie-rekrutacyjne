<?php
// Automatyczne formatowanie VS Code (bez użycia AI)

//zad 1
// Przyjmijmy, że dni zaczynają się od poniedziałku, a niedziele są oznaczone kolorem czerwonym. Twoim
// zadaniem jest napisanie krótkiego programu w języku PHP, który umożliwi wygenerowanie w html
// takiej kartki z kalendarza dla dowolnie podanego miesiąca i roku.

function zwrocKalendarz($miesiac, $rok)
{
    // zwraca string z tabela
    // sprawdzam ile dni ma MC i od jakiego dnia sie zaczyna
    // https://www.php.net/manual/en/function.mktime.php
    // mktime(
    //     int $hour,
    //     ?int $minute = null,
    //     ?int $second = null,
    //     ?int $month = null,
    //     ?int $day = null,
    //     ?int $year = null
    // ): int|false
    $pierwszyDzien = mktime(0, 0, 0, $miesiac, 1, $rok);
    // date(string $format, ?int $timestamp = null): string
    // t - total, N - number
    $ileDni = date('t', $pierwszyDzien);
    $dzienTygodnia = date('N', $pierwszyDzien); // 1=pn, 7=nd

    $nazwyMiesiecy = 'Styczeń Luty Marzec Kwiecień Maj Czerwiec Lipiec Sierpień Wrzesień Październik Listopad Grudzień';
    $miesiace = explode(' ', $nazwyMiesiecy); // tablica Mcy
    $nazwaMiesiaca = $miesiace[$miesiac - 1]; // -1 bo index tablicy od 0;
    //////////////////////////////////////////////////////////////////////////////////////
    // style, bedzie sie duplilowac przy wielu "echo zwrocKalendarz", ale na potrzeby zadania nie zabezpieczam tego
    echo "<style>
        * { font-weight: bold; }
        table { border-collapse: collapse; margin: 20px; }
        td, th { border: 1px solid black; padding: 10px; text-align: center; width: 50px; }
        .niedziela { color: red; font-weight: bold; }
        th { background-color: #f0f0f0; }
    </style>";
    //////////////////////////////////////////////////////////////////////////////////////
    // puste komorki z przodu jesli numeracja nie zaczyna sie od PN
    $pusteKomorkiPrzed = '';
    for ($i = 1; $i < $dzienTygodnia; $i++) {
        $pusteKomorkiPrzed .= "<td>&nbsp;</td>";
    }
    //////////////////////////////////////////////////////////////////////////////////////
    // wypelniamy dni miesiaca
    $wypelnioneKomorki = '';
    for ($dzien = 1; $dzien <= $ileDni; $dzien++) {
        // sprawdzam ktory dzien tygodnia
        //https://www.php.net/manual/en/datetime.construct.php
        $data = new DateTime("$rok-$miesiac-$dzien");
        $aktualnyDzien = $data->format('N'); //N - numbery

        if ($aktualnyDzien == 7) { //nd wymaga koloru czeronego
            $wypelnioneKomorki .= "<td class='niedziela'>" . $dzien . "</td>";
        } else {
            $wypelnioneKomorki .= "<td>" . $dzien . "</td>";
        }

        // jezeli to niedziela i nie jest to ostatni dzien miesiaca to nowy wiersz
        if ($aktualnyDzien == 7 && $dzien < $ileDni) {
            $wypelnioneKomorki .= "</tr><tr>";
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////
    // dopelniamy ostatni wiersz do konca jesli jest miejsce
    $pusteKomorkiPo = '';
    $ostatniDzien = ($dzienTygodnia + $ileDni - 2) % 7 + 1;
    if ($ostatniDzien != 7) {
        for ($i = $ostatniDzien; $i < 7; $i++) {
            $pusteKomorkiPo .= "<td>&nbsp;</td>";
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////
    // nazwy dni tygodnia
    $el = 'Pn.Wt.Śr.Cz.Pt.SO.N';
    $el = explode('.', $el);
    $stylDniaBazy = "flex: 1; font-weight: bold; padding: 8px; text-align: center; font-size: 12px;";

    $divyDni = '';
    foreach ($el as $e) {
        if ($e == 'N') {
            $divyDni .= "<div style='" . $stylDniaBazy . " color: white; background-color: #e53e3e;'>" . $e . "</div>";
        } else {
            $divyDni .= "<div style='" . $stylDniaBazy . " color: white; background-color: #666;'>" . $e . "</div>";
        }
    }
    $nazwaDni = "<tr>"
        . "<td colspan='7' style='padding: 0; border: 1px solid black;'>"
        . "<div style='display: flex;'>" . $divyDni . "</div>"
        . "</td></tr>";
    //////////////////////////////////////////////////////////////////////////////////////
    // opakowanie w <tr>
    // wspólne style
    $stylBazy = "font-size: 18px; padding: 12px; font-weight: 900; text-shadow: 1px 1px 0px rgba(0,0,0,0.3);";
    $stylMiesiaca = "flex: 5; color: red; text-align: left; " . $stylBazy;
    $stylRoku = "flex: 2; color: black; text-align: right; " . $stylBazy;

    $naglowekTabeli = "<tr>"
        . "<td colspan='7' style='padding: 0; border: 1px solid black; font-weight: bold;'>"
        . "<div style='display: flex;'>"
        . "<div style='" . $stylMiesiaca . "'>" . $nazwaMiesiaca . "</div>"
        . "<div style='" . $stylRoku . "'>" . $rok . "</div>"
        . "</div></td>"
        . "</tr>";
    $nazwaDni = "<tr>" . $nazwaDni . "</tr>";
    $wypelnioneKomorki = "<tr>" . $pusteKomorkiPrzed . $wypelnioneKomorki . $pusteKomorkiPo . "</tr>";

    return "<table>"
        . $naglowekTabeli // mc + rok
        . $nazwaDni // od PN do N
        . $wypelnioneKomorki // dni mc
        . "</table>";
}
//////////////////////////////////////////////////////////////////////////////////////
// test w html: php zadanie1.php > test.html | firefox test.html
echo "grudzien-2021<br>";
echo zwrocKalendarz(12, 2021);
echo "Luty-2024-rok przestepny<br>";
echo zwrocKalendarz(2, 2024);
echo "styczen-2025<br>";
echo zwrocKalendarz(1, 2025);
