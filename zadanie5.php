<?php
// Automatyczne formatowanie VS Code (bez użycia AI)

// zadanie obieg pism - sqlite bo latwiej testowac
// autostart

copy(__FILE__, 'obieg_pism.php');
exec('php -S localhost:8002 obieg_pism.php');
//////////////////////////////////////////////////////////////////////////////////////
$db = new PDO('sqlite:obieg_pism.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tabele = [
    'uzytkownicy' => [
        'id' => 'INTEGER PRIMARY KEY',
        'imie' => 'TEXT',
        'nazwisko' => 'TEXT',
        'email' => 'TEXT UNIQUE',
        'stanowisko' => 'TEXT' 
    ],
    'pisma' => [
        'id' => 'INTEGER PRIMARY KEY',
        'numer' => 'TEXT UNIQUE',
        'temat' => 'TEXT',
        'tresc' => 'TEXT',
        'adresat' => 'TEXT',
        'autor_id' => 'INTEGER',
        'status' => 'TEXT DEFAULT "nowe"',
        'akceptujacy_id' => 'INTEGER',
        'zatwierdzajacy_id' => 'INTEGER',
        'czy_zastepstwo_akc' => 'INTEGER DEFAULT 0',
        'czy_zastepstwo_zatw' => 'INTEGER DEFAULT 0',
        'utworzono' => 'TEXT DEFAULT CURRENT_TIMESTAMP'
    ],
    'urlopy' => [
        'id' => 'INTEGER PRIMARY KEY',
        'uzytkownik_id' => 'INTEGER',
        'data_od' => 'TEXT',
        'data_do' => 'TEXT',
        'typ' => 'TEXT'
    ],
    'zastepstwa' => [
        'id' => 'INTEGER PRIMARY KEY',
        'zastepowany_id' => 'INTEGER',
        'zastepca_id' => 'INTEGER',
        'data_od' => 'TEXT',
        'data_do' => 'TEXT'
    ],
    'historia' => [
        'id' => 'INTEGER PRIMARY KEY',
        'pismo_id' => 'INTEGER',
        'uzytkownik_id' => 'INTEGER',
        'akcja' => 'TEXT',
        'data' => 'TEXT DEFAULT CURRENT_TIMESTAMP'
    ]
];

// Tworzenie tabel
foreach ($tabele as $nazwa => $pola) {
    $def = [];
    foreach ($pola as $pole => $typ) {
        $def[] = "$pole $typ";
    }
    $sql = "CREATE TABLE IF NOT EXISTS $nazwa (" . implode(', ', $def) . ")";
    $db->exec($sql);
}

// Dane testowe - tylko przy pierwszym uruchomieniu
$czy_puste = $db->query("SELECT COUNT(*) FROM uzytkownicy")->fetchColumn();
if ($czy_puste == 0) {
    $testowe = [
        // Użytkownicy
        "INSERT INTO uzytkownicy (imie, nazwisko, email, stanowisko) VALUES 
            ('Jan', 'Kowalski', 'jan@firma.pl', 'pracownik'),
            ('Anna', 'Nowak', 'anna@firma.pl', 'kierownik'),
            ('Piotr', 'Wiśniewski', 'piotr@firma.pl', 'dyrektor'),
            ('Maria', 'Zielińska', 'maria@firma.pl', 'kierownik'),
            ('Tomasz', 'Mazur', 'tomasz@firma.pl', 'dyrektor')",
        
        // Przykładowy urlop kierownika Anny
        "INSERT INTO urlopy (uzytkownik_id, data_od, data_do, typ) VALUES 
            (2, '2025-06-02', '2025-06-10', 'wypoczynkowy')",
        
        // Maria zastępuje Annę
        "INSERT INTO zastepstwa (zastepowany_id, zastepca_id, data_od, data_do) VALUES 
            (2, 4, '2025-06-02', '2025-06-10')"
    ];
    
    foreach ($testowe as $sql) {
        $db->exec($sql);
    }
}

//////////////////////////////////////////////////////////////////////////////////////
// FUNKCJE POMOCNICZE

function generujNumer($db) {
    $rok = date('Y');
    $sql = "SELECT COUNT(*) + 1 FROM pisma WHERE numer LIKE 'PW/$rok/%'";
    $nr = $db->query($sql)->fetchColumn();
    return sprintf("PW/%s/%03d", $rok, $nr);
}

function czyNaUrlopie($user_id, $db) {
    $dzis = date('Y-m-d');
    $sql = "SELECT COUNT(*) FROM urlopy 
            WHERE uzytkownik_id = ? 
            AND ? BETWEEN data_od AND data_do";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $dzis]);
    return $stmt->fetchColumn() > 0;
}

function znajdzZastepce($user_id, $db) {
    $dzis = date('Y-m-d');
    $sql = "SELECT zastepca_id FROM zastepstwa 
            WHERE zastepowany_id = ? 
            AND ? BETWEEN data_od AND data_do
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $dzis]);
    return $stmt->fetchColumn();
}

function ktoMozeObsluzyc($stanowisko, $db) {
    // Znajdź główną osobę na stanowisku
    $sql = "SELECT id FROM uzytkownicy WHERE stanowisko = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$stanowisko]);
    $glowny = $stmt->fetchColumn();
    
    if (!$glowny) return false;
    
    // Sprawdź czy jest na urlopie
    if (czyNaUrlopie($glowny, $db)) {
        // Szukaj zastępcy
        $zastepca = znajdzZastepce($glowny, $db);
        return $zastepca ?: false;
    }
    
    return $glowny;
}

//////////////////////////////////////////////////////////////////////////////////////
// FUNKCJE BIZNESOWE

function utworzPismo($autor_id, $temat, $tresc, $adresat, $db) {
    $numer = generujNumer($db);
    $sql = "INSERT INTO pisma (numer, temat, tresc, adresat, autor_id, status) 
            VALUES (?, ?, ?, ?, ?, 'do_akceptacji')";
    $stmt = $db->prepare($sql);
    $stmt->execute([$numer, $temat, $tresc, $adresat, $autor_id]);
    
    $pismo_id = $db->lastInsertId();
    
    // Dodaj do historii
    $sql = "INSERT INTO historia (pismo_id, uzytkownik_id, akcja) VALUES (?, ?, 'utworzenie')";
    $db->prepare($sql)->execute([$pismo_id, $autor_id]);
    
    return $pismo_id;
}

function akceptujPismo($pismo_id, $user_id, $db) {
    // Sprawdź czy to kierownik lub jego zastępca
    $kto_moze = ktoMozeObsluzyc('kierownik', $db);
    if ($kto_moze != $user_id) {
        return ['sukces' => false, 'komunikat' => 'Nie masz uprawnień do akceptacji'];
    }
    
    // Sprawdź status pisma
    $sql = "SELECT status FROM pisma WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$pismo_id]);
    $status = $stmt->fetchColumn();
    
    if ($status != 'do_akceptacji') {
        return ['sukces' => false, 'komunikat' => 'Pismo nie jest w statusie do akceptacji'];
    }
    
    // Sprawdź czy to zastępstwo
    $glowny_kierownik = $db->query("SELECT id FROM uzytkownicy WHERE stanowisko = 'kierownik' LIMIT 1")->fetchColumn();
    $czy_zastepstwo = ($user_id != $glowny_kierownik) ? 1 : 0;
    
    // Aktualizuj pismo
    $sql = "UPDATE pisma SET 
            status = 'zaakceptowane', 
            akceptujacy_id = ?,
            czy_zastepstwo_akc = ?
            WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $czy_zastepstwo, $pismo_id]);
    
    // Dodaj do historii
    $sql = "INSERT INTO historia (pismo_id, uzytkownik_id, akcja) VALUES (?, ?, 'akceptacja')";
    $db->prepare($sql)->execute([$pismo_id, $user_id]);
    
    return ['sukces' => true, 'komunikat' => 'Pismo zaakceptowane'];
}

function zatwierdzPismo($pismo_id, $user_id, $db) {
    // Sprawdź czy to dyrektor lub jego zastępca
    $kto_moze = ktoMozeObsluzyc('dyrektor', $db);
    if ($kto_moze != $user_id) {
        return ['sukces' => false, 'komunikat' => 'Nie masz uprawnień do zatwierdzenia'];
    }
    
    // Sprawdź status pisma
    $sql = "SELECT status FROM pisma WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$pismo_id]);
    $status = $stmt->fetchColumn();
    
    if ($status != 'zaakceptowane') {
        return ['sukces' => false, 'komunikat' => 'Pismo nie jest zaakceptowane'];
    }
    
    // Sprawdź czy to zastępstwo
    $glowny_dyrektor = $db->query("SELECT id FROM uzytkownicy WHERE stanowisko = 'dyrektor' LIMIT 1")->fetchColumn();
    $czy_zastepstwo = ($user_id != $glowny_dyrektor) ? 1 : 0;
    
    // Aktualizuj pismo
    $sql = "UPDATE pisma SET 
            status = 'zatwierdzone', 
            zatwierdzajacy_id = ?,
            czy_zastepstwo_zatw = ?
            WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $czy_zastepstwo, $pismo_id]);
    
    // Dodaj do historii
    $sql = "INSERT INTO historia (pismo_id, uzytkownik_id, akcja) VALUES (?, ?, 'zatwierdzenie')";
    $db->prepare($sql)->execute([$pismo_id, $user_id]);
    
    return ['sukces' => true, 'komunikat' => 'Pismo zatwierdzone'];
}

//////////////////////////////////////////////////////////////////////////////////////
// OBSŁUGA FORMULARZY

$komunikat = '';
$typ_komunikatu = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['utworz_pismo'])) {
            $autor_id = $_POST['autor_id'];
            $temat = $_POST['temat'];
            $tresc = $_POST['tresc'];
            $adresat = $_POST['adresat'];
            
            if ($autor_id && $temat && $tresc && $adresat) {
                $id = utworzPismo($autor_id, $temat, $tresc, $adresat, $db);
                $komunikat = "Utworzono pismo nr " . generujNumer($db);
                $typ_komunikatu = 'sukces';
            } else {
                $komunikat = "Wypełnij wszystkie pola";
                $typ_komunikatu = 'blad';
            }
        }
        
        if (isset($_POST['akceptuj'])) {
            $pismo_id = $_POST['pismo_id'];
            $user_id = $_POST['user_id'];
            $wynik = akceptujPismo($pismo_id, $user_id, $db);
            $komunikat = $wynik['komunikat'];
            $typ_komunikatu = $wynik['sukces'] ? 'sukces' : 'blad';
        }
        
        if (isset($_POST['zatwierdz'])) {
            $pismo_id = $_POST['pismo_id'];
            $user_id = $_POST['user_id'];
            $wynik = zatwierdzPismo($pismo_id, $user_id, $db);
            $komunikat = $wynik['komunikat'];
            $typ_komunikatu = $wynik['sukces'] ? 'sukces' : 'blad';
        }
        
    } catch (Exception $e) {
        $komunikat = "Błąd: " . $e->getMessage();
        $typ_komunikatu = 'blad';
    }
}

// Pobierz dane do wyświetlenia
$uzytkownicy = $db->query("SELECT * FROM uzytkownicy ORDER BY nazwisko")->fetchAll();
$pisma = $db->query("
    SELECT p.*, 
           u1.imie || ' ' || u1.nazwisko as autor,
           u2.imie || ' ' || u2.nazwisko as akceptujacy,
           u3.imie || ' ' || u3.nazwisko as zatwierdzajacy
    FROM pisma p
    JOIN uzytkownicy u1 ON p.autor_id = u1.id
    LEFT JOIN uzytkownicy u2 ON p.akceptujacy_id = u2.id
    LEFT JOIN uzytkownicy u3 ON p.zatwierdzajacy_id = u3.id
    ORDER BY p.id DESC
")->fetchAll();

// Aktualne zastępstwa
$dzis = date('Y-m-d');
$zastepstwa = $db->prepare("
    SELECT u1.imie || ' ' || u1.nazwisko as zastepowany,
           u1.stanowisko as stanowisko_zastepowanego,
           u2.imie || ' ' || u2.nazwisko as zastepca,
           z.data_od, z.data_do
    FROM zastepstwa z
    JOIN uzytkownicy u1 ON z.zastepowany_id = u1.id
    JOIN uzytkownicy u2 ON z.zastepca_id = u2.id
    WHERE ? BETWEEN z.data_od AND z.data_do
");
$zastepstwa->execute([$dzis]);
$aktywne_zastepstwa = $zastepstwa->fetchAll();

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>System Obiegu Pism Wychodzących</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        h1 { 
            color: #333; 
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        h2 { 
            color: #555; 
            margin-top: 30px;
        }
        
        table { 
            border-collapse: collapse; 
            width: 100%;
            margin: 15px 0;
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 10px; 
            text-align: left;
        }
        
        th {
            background-color: #007bff;
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-nowe { background-color: #ffeeba; }
        .status-do_akceptacji { background-color: #ffeeba; }
        .status-zaakceptowane { background-color: #d4edda; }
        .status-zatwierdzone { background-color: #d1ecf1; }
        
        form.formularz {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #dee2e6;
        }
        
        label {
            display: inline-block;
            width: 120px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        input[type="text"], textarea, select {
            width: 300px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        textarea {
            vertical-align: top;
            height: 100px;
        }
        
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        button.akceptuj {
            background-color: #28a745;
        }
        
        button.akceptuj:hover {
            background-color: #218838;
        }
        
        button.zatwierdz {
            background-color: #17a2b8;
        }
        
        button.zatwierdz:hover {
            background-color: #138496;
        }
        
        .komunikat {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .komunikat.sukces {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .komunikat.blad {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .akcje {
            display: inline-block;
        }
        
        .akcje select {
            width: auto;
            margin-right: 5px;
        }
        
        .zastepstwo-info {
            color: #856404;
            font-style: italic;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Obiegu Pism Wychodzących</h1>
        
        <?php if ($komunikat): ?>
            <div class="komunikat <?= $typ_komunikatu ?>">
                <?= htmlspecialchars($komunikat) ?>
            </div>
        <?php endif; ?>
        
        <!-- Informacja o zastępstwach -->
        <?php if ($aktywne_zastepstwa): ?>
            <div class="info-box">
                <h3>Aktywne zastępstwa:</h3>
                <?php foreach ($aktywne_zastepstwa as $z): ?>
                    <p>
                        <strong><?= $z['zastepowany'] ?></strong> (<?= $z['stanowisko_zastepowanego'] ?>) 
                        → zastępuje: <strong><?= $z['zastepca'] ?></strong>
                        (od <?= $z['data_od'] ?> do <?= $z['data_do'] ?>)
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formularz nowego pisma -->
        <h2>Nowe pismo wychodzące</h2>
        <form method="post" class="formularz">
            <div>
                <label>Autor:</label>
                <select name="autor_id" required>
                    <option value="">-- wybierz --</option>
                    <?php foreach ($uzytkownicy as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= $u['imie'] ?> <?= $u['nazwisko'] ?> (<?= $u['stanowisko'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Temat:</label>
                <input type="text" name="temat" required>
            </div>
            
            <div>
                <label>Adresat:</label>
                <input type="text" name="adresat" required>
            </div>
            
            <div>
                <label>Treść:</label>
                <textarea name="tresc" required></textarea>
            </div>
            
            <button type="submit" name="utworz_pismo">Utwórz pismo</button>
        </form>
        
        <!-- Lista pism -->
        <h2>Lista pism</h2>
        <table>
            <thead>
                <tr>
                    <th>Nr</th>
                    <th>Temat</th>
                    <th>Adresat</th>
                    <th>Autor</th>
                    <th>Status</th>
                    <th>Akceptacja</th>
                    <th>Zatwierdzenie</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pisma as $p): ?>
                    <tr class="status-<?= $p['status'] ?>">
                        <td><?= $p['numer'] ?></td>
                        <td><?= htmlspecialchars($p['temat']) ?></td>
                        <td><?= htmlspecialchars($p['adresat']) ?></td>
                        <td><?= $p['autor'] ?></td>
                        <td><strong><?= $p['status'] ?></strong></td>
                        <td>
                            <?= $p['akceptujacy'] ?: '-' ?>
                            <?php if ($p['czy_zastepstwo_akc']): ?>
                                <br><span class="zastepstwo-info">(zastępstwo)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $p['zatwierdzajacy'] ?: '-' ?>
                            <?php if ($p['czy_zastepstwo_zatw']): ?>
                                <br><span class="zastepstwo-info">(zastępstwo)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['status'] == 'do_akceptacji'): ?>
                                <form method="post" class="akcje">
                                    <input type="hidden" name="pismo_id" value="<?= $p['id'] ?>">
                                    <select name="user_id" required>
                                        <option value="">-- kto akceptuje --</option>
                                        <?php 
                                        $kto_kierownik = ktoMozeObsluzyc('kierownik', $db);
                                        foreach ($uzytkownicy as $u):
                                            if ($u['id'] == $kto_kierownik):
                                        ?>
                                            <option value="<?= $u['id'] ?>">
                                                <?= $u['imie'] ?> <?= $u['nazwisko'] ?>
                                            </option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                    <button type="submit" name="akceptuj" class="akceptuj">Akceptuj</button>
                                </form>
                            <?php elseif ($p['status'] == 'zaakceptowane'): ?>
                                <form method="post" class="akcje">
                                    <input type="hidden" name="pismo_id" value="<?= $p['id'] ?>">
                                    <select name="user_id" required>
                                        <option value="">-- kto zatwierdza --</option>
                                        <?php 
                                        $kto_dyrektor = ktoMozeObsluzyc('dyrektor', $db);
                                        foreach ($uzytkownicy as $u):
                                            if ($u['id'] == $kto_dyrektor):
                                        ?>
                                            <option value="<?= $u['id'] ?>">
                                                <?= $u['imie'] ?> <?= $u['nazwisko'] ?>
                                            </option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                    <button type="submit" name="zatwierdz" class="zatwierdz">Zatwierdź</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Historia pisma - przykład dla pierwszego pisma -->
        <?php if (count($pisma) > 0): ?>
            <h2>Historia przykładowego pisma</h2>
            <?php
            $pismo_id = $pisma[0]['id'];
            $historia = $db->prepare("
                SELECT h.*, u.imie || ' ' || u.nazwisko as uzytkownik
                FROM historia h
                JOIN uzytkownicy u ON h.uzytkownik_id = u.id
                WHERE h.pismo_id = ?
                ORDER BY h.data
            ");
            $historia->execute([$pismo_id]);
            $wpisy = $historia->fetchAll();
            ?>
            
            <?php if ($wpisy): ?>
                <table>
                    <tr>
                        <th>Data</th>
                        <th>Użytkownik</th>
                        <th>Akcja</th>
                    </tr>
                    <?php foreach ($wpisy as $w): ?>
                        <tr>
                            <td><?= $w['data'] ?></td>
                            <td><?= $w['uzytkownik'] ?></td>
                            <td><?= $w['akcja'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>