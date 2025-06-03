<?php
// Automatyczne formatowanie VS Code (bez użycia AI)

//zad 3
// Masz do przygotowania formularz do rejestracji dla użytkownika. W formularzu tym, dla osoby
// fizycznej, wymagane są takie dane jak imię, adres e-mail, data urodzenia, w przypadku firm: nazwa
// firmy, adres e-mail, NIP. Zaproponuj pola w tabeli bazy danych, a także możliwe metody weryfikacyjne,
// aby nie dopuścić do redundancji danych, błędów w danych, niespójności w danych.
//////////////////////////////////////////////////////////////////////////////////////
// baza sqlite bo łatwiej testować bez mysql
// autostart zadanie3.php
copy(__FILE__, 'index.php');
exec('php -S localhost:8001');
//////////////////////////////////////////////////////////////////////////////////////

$db = new PDO('sqlite:baza.db');

$kolumny = [
    'id' => 'INTEGER PRIMARY KEY',
    'typ' => 'TEXT',
    'email' => 'TEXT UNIQUE',
    'imie' => 'TEXT',
    'data_ur' => 'TEXT',
    'firma' => 'TEXT',
    'nip' => 'TEXT'
];

$definicja = [];
foreach ($kolumny as $k => $v) {
    $definicja[] = "$k $v";
}
$sql_create = "CREATE TABLE IF NOT EXISTS users (" . implode(', ', $definicja) . ")";
$db->exec($sql_create);
//////////////////////////////////////////////////////////////////////////////////////
$msg = '';
if ($_POST) {
    if (isset($_POST['clear'])) {// na potrzeby testow
        $db->exec("DELETE FROM users");
        $msg = 'baza wyczyszczona';
    }
    $dane = $_POST;
    $email = $dane['email'];
    $typ = $dane['typ'];

    $sprawdzenia = [
        'email' => function ($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL) ? '' : 'zly email';
        },
        'duplikat' => function ($e) use ($db) {
            $q = $db->prepare("SELECT * FROM users WHERE email=?");
            $q->execute([$e]);
            return $q->fetch() ? 'email juz jest' : '';
        }
    ];

    $bledy = [];
    foreach ($sprawdzenia as $nazwa => $funkcja) {
        $wynik = $funkcja($email);
        if ($wynik) $bledy[] = $wynik;
    }

    $pola_wymagane = [
        'osoba' => ['imie'],
        'firma' => ['firma']
    ];

    foreach ($pola_wymagane[$typ] as $pole) {
        if (!$dane[$pole]) {
            $bledy[] = "brak: $pole";
        }
    }

    $konfiguracja = [
        'osoba' => [
            'pola' => 'typ,email,imie,data_ur',
            'klucze' => ['typ', 'email', 'imie', 'data_ur']
        ],
        'firma' => [
            'pola' => 'typ,email,firma,nip',
            'klucze' => ['typ', 'email', 'firma', 'nip']
        ]
    ];

    if (empty($bledy)) {
        $cfg = $konfiguracja[$typ];
        $wartosci = [];
        
        foreach ($cfg['klucze'] as $k) {
            $wartosci[] = $k == 'typ' ? $typ : ($k == 'email' ? $email : $dane[$k]);
        }

        $znaki = implode(',', array_fill(0, count($wartosci), '?'));
        $sql = "INSERT INTO users ({$cfg['pola']}) VALUES ($znaki)";
        $db->prepare($sql)->execute($wartosci);
        $msg = 'ok';
    } else {
        $msg = implode(', ', $bledy);
    }
}
?>
<html>

<body>

    <h2>Formularz</h2>

    <?php if ($msg): ?>
        <p><?= $msg ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="radio" name="typ" value="osoba" checked onclick="pokaz()"> Osoba
        <input type="radio" name="typ" value="firma" onclick="pokaz()"> Firma
        <br><br>

        Email: <input name="email" type="email" required>
        <br><br>
        <div id="osoba">
            Imie: <input name="imie"><br>
            Data: <input name="data_ur" type="date"><br>
        </div>
        <div id="firma" style="display:none">
            Firma: <input name="firma"><br>
            NIP: <input name="nip"><br>
        </div>

        <input type="submit" value="Zapisz">
    </form>
    <form method="post">
        <input type="submit" name="clear" value="Wyczyść bazę" 
            onclick="return confirm('Na pewno?')">
    </form>

    <h3>Lista</h3>
    <?php
    // wyświetlanie listy zapisanych użytkowników w tabeli
    $naglowki = explode('.', 'Typ.Email.Imie.Data.Firma.NIP');
    echo "<table border='1'><tr>";
    foreach ($naglowki as $n) {
        echo "<th>$n</th>";
    }
    echo "</tr>";
    //////////////////////////////////////////////////////////////////////////////////////
    $el = explode('.', 'typ.email.imie.data_ur.firma.nip');
    $lista = $db->query("SELECT * FROM users")->fetchAll();
    // generowanie wierszy tabeli
    foreach ($lista as $u) {
        echo "<tr>";
        foreach ($el as $e) {
            echo "<td>" . $u[$e] . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    ?>

    <script>
        // przełączanie widoczności divów
        function pokaz() {
            var typ = document.querySelector('input[name="typ"]:checked').value;
            document.getElementById('osoba').style.display = typ == 'osoba' ? 'block' : 'none';
            document.getElementById('firma').style.display = typ == 'firma' ? 'block' : 'none';
        }
    </script>

</body>

</html>