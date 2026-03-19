<?php

// RÉCUPÉRATION DES RÉPONSES DU FORMULAIRE
$reponses = [];

for ($i = 0; $i < 7; $i++) {
    if (isset($_POST["q$i"])) {
        $reponses[$i] = (int) $_POST["q$i"];
    } else {
        // sécurité si une réponse manque
        $reponses[$i] = 0;
    }
}


// FONCTION : calculer le syndrome
function calculerSyndrome($reponses) {
    $s1 = ($reponses[0] + $reponses[2] + $reponses[4] + $reponses[6]) % 2;
    $s2 = ($reponses[1] + $reponses[2] + $reponses[4] + $reponses[5]) % 2;
    $s3 = ($reponses[3] + $reponses[4] + $reponses[5] + $reponses[6]) % 2;

    return [$s1, $s2, $s3];
}


// FONCTION : trouver la question mensongère
function trouverMensonge($reponses) {
    $syndrome = calculerSyndrome($reponses);

    $aux = $syndrome[0] + 2 * $syndrome[1] + 4 * $syndrome[2];

    $table = [
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        7 => 5,
        6 => 6,
        5 => 7,
    ];

    return $table[$aux];
}



// FONCTION : trouver le bon personnage
function trouverPersonnage($reponses) {

    $question_mensonge = trouverMensonge($reponses);

    // Correction si mensonge
    if ($question_mensonge != 0) {
        $reponses[$question_mensonge - 1] = ($reponses[$question_mensonge - 1] + 1) % 2;
    }

    // Conversion des 4 bits en nombre (0 à 15)
    $index = 0;

    for ($i = 0; $i < 4; $i++) {
        $index += $reponses[3 - $i] * pow(2, $i);
    }

    return (int)$index;
}



// TRAITEMENT

$mensonge = trouverMensonge($reponses);
$perso = trouverPersonnage($reponses);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultat</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Résultat</h1>

<?php
// Affichage mensonge
if ($mensonge == 0) {
    echo "<p>Vous n'avez pas menti.</p>";
} else {
    echo "<p>Vous avez menti à la question <strong>$mensonge</strong>.</p>";
}

// Affichage personnage
echo "<h2>Personnage trouvé :</h2>";
echo "<img src='pictures/$perso.jpg' width='200'>";

// Affichage debug 
echo "<h3>Détails :</h3>";
echo "<p>Réponses : " . implode(" ", $reponses) . "</p>";

?>

<br><br>

<a href="index.php">Rejouer</a>

</body>
</html>