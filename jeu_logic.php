<?php
/**
 * jeu_logic.php
 * API PHP côté serveur pour la logique du jeu Qui est-ce ?
 * Appelée en AJAX par le JavaScript de la page de jeu.
 *
 * Endpoints (paramètre GET "action") :
 *   - get_personnages  → renvoie les 16 personnages mélangés (sans les bits, pour ne pas tricher)
 *   - verifier         → reçoit les 7 réponses du joueur, renvoie le résultat + détection mensonge
 */

header('Content-Type: application/json; charset=UTF-8');
// Autoriser les appels depuis la même origine uniquement
header('X-Content-Type-Options: nosniff');

define('DATA_FILE', __DIR__ . '/personnages.json');

// ── Utilitaires ───────────────────────────────────────────────────────────────

function reponse(bool $succes, array $data = [], string $erreur = ''): void {
    echo json_encode([
        'succes' => $succes,
        'erreur' => $erreur,
        'data'   => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function chargerPersonnages(): array {
    if (!file_exists(DATA_FILE)) return [];
    return json_decode(file_get_contents(DATA_FILE), true) ?? [];
}

// ── Logique du code de Hamming (détection d'un mensonge) ─────────────────────

/**
 * Calcule le syndrome de Hamming à partir des 7 réponses du joueur.
 * v = tableau de 7 bits (0 ou 1), dans l'ordre :
 *   [lunettes, moustache, chapeau, cheveux, boucle, barbe, noeud]
 *
 * Les 3 bits du syndrome s = [s1, s2, s3] permettent de détecter
 * quelle question a reçu un mensonge (ou 0 = pas de mensonge).
 *
 * Relations de parité utilisées :
 *   s1 = v[0] XOR v[2] XOR v[4] XOR v[6]   (positions 1,3,5,7)
 *   s2 = v[1] XOR v[2] XOR v[4] XOR v[5]   (positions 2,3,5,6)
 *   s3 = v[3] XOR v[4] XOR v[5] XOR v[6]   (positions 4,5,6,7)
 */
function syndrome(array $v): array {
    $s1 = ($v[0] + $v[2] + $v[4] + $v[6]) % 2;
    $s2 = ($v[1] + $v[2] + $v[4] + $v[5]) % 2;
    $s3 = ($v[3] + $v[4] + $v[5] + $v[6]) % 2;
    return [$s1, $s2, $s3];
}

/**
 * Renvoie le numéro (1–7) de la question mensongère, ou 0 si aucun mensonge.
 *
 * La valeur aux = s1 + 2*s2 + 4*s3 identifie la position :
 *   aux → question mensongère
 *    0  → pas de mensonge
 *    1  → Q1 (lunettes)
 *    2  → Q2 (moustache)
 *    3  → Q3 (chapeau)
 *    4  → Q4 (cheveux)
 *    7  → Q5 (boucle d'oreilles)
 *    6  → Q6 (barbe)
 *    5  → Q7 (nœud papillon)
 */
function erreur(array $v): int {
    $s   = syndrome($v);
    $aux = $s[0] + 2 * $s[1] + 4 * $s[2];
    $table = [0=>0, 1=>1, 2=>2, 3=>3, 4=>4, 7=>5, 6=>6, 5=>7];
    return $table[$aux] ?? 0;
}

/**
 * Corrige le mensonge éventuel et renvoie l'index (0–15) du personnage trouvé.
 * L'index est lu sur les 4 bits de données : v[3], v[2], v[1], v[0] en binaire.
 *    n = v[3]*8 + v[2]*4 + v[1]*2 + v[0]
 */
function bonPersonnage(array $v): int {
    $e = erreur($v);
    if ($e !== 0) {
        // Inverser le bit menteur
        $v[$e - 1] = ($v[$e - 1] + 1) % 2;
    }
    // Les 4 bits de données sont aux positions 3,2,1,0 (indices 3,2,1,0 du tableau)
    $n = 0;
    for ($i = 0; $i < 4; $i++) {
        $n += $v[3 - $i] * (int)pow(2, $i);
    }
    return $n;
}

// ── Routage des actions ───────────────────────────────────────────────────────

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── GET /jeu_logic.php?action=get_personnages ─────────────────────────────
    // Renvoie les personnages dans un ordre aléatoire.
    // On envoie l'id, le nom et l'image — PAS les bits (le joueur ne doit pas les voir).
    case 'get_personnages':
        $personnages = chargerPersonnages();

        if (count($personnages) !== 16) {
            reponse(false, [], 'Il faut exactement 16 personnages. Actuellement : ' . count($personnages));
        }

        // Mélange façon Fisher-Yates
        $indices = range(0, 15);
        shuffle($indices);

        $result = [];
        foreach ($indices as $pos => $idx) {
            $p = $personnages[$idx];
            $result[] = [
                'position' => $pos,      // position dans la grille affichée
                'id'       => $p['id'],  // id réel (= index dans le fichier JSON)
                'nom'      => $p['nom'],
                'image'    => 'images/' . $p['image'],
            ];
        }

        reponse(true, ['personnages' => $result]);
        break;


    // ── POST /jeu_logic.php?action=verifier ───────────────────────────────────
    // Corps JSON attendu : { "reponses": [0,1,0,1,0,0,1] }
    // Renvoie : personnage trouvé, numéro du mensonge (0 = aucun), message
    case 'verifier':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            reponse(false, [], 'Méthode non autorisée. Utilisez POST.');
        }

        $body = file_get_contents('php://input');
        $json = json_decode($body, true);

        if (!isset($json['reponses']) || !is_array($json['reponses'])) {
            reponse(false, [], 'Paramètre "reponses" manquant ou invalide.');
        }

        $reponses = array_map('intval', $json['reponses']);

        if (count($reponses) !== 7) {
            reponse(false, [], 'Il faut exactement 7 réponses.');
        }

        foreach ($reponses as $bit) {
            if ($bit !== 0 && $bit !== 1) {
                reponse(false, [], 'Chaque réponse doit être 0 ou 1.');
            }
        }

        // Calcul
        $num_mensonge  = erreur($reponses);
        $index_perso   = bonPersonnage($reponses);

        // Récupérer le personnage correspondant
        $personnages = chargerPersonnages();
        $perso_trouve = null;
        foreach ($personnages as $p) {
            if ($p['id'] === $index_perso) {
                $perso_trouve = $p;
                break;
            }
        }

        if (!$perso_trouve) {
            reponse(false, [], "Personnage introuvable pour l'index $index_perso.");
        }

        // Construction du message
        $labels_questions = [
            1 => 'lunettes',
            2 => 'moustache',
            3 => 'chapeau',
            4 => 'cheveux',
            5 => "boucle d'oreilles",
            6 => 'barbe',
            7 => 'nœud papillon',
        ];

        if ($num_mensonge === 0) {
            $message = "Vous n'avez pas menti. Le personnage est : {$perso_trouve['nom']}.";
        } else {
            $label = $labels_questions[$num_mensonge];
            $message = "Vous avez menti à la question $num_mensonge ($label). "
                     . "Malgré cela, le personnage trouvé est : {$perso_trouve['nom']}.";
        }

        reponse(true, [
            'num_mensonge'  => $num_mensonge,       // 0 = pas de mensonge, 1–7 = numéro question
            'index_perso'   => $index_perso,         // index 0–15 du personnage
            'nom_perso'     => $perso_trouve['nom'],
            'image_perso'   => 'images/' . $perso_trouve['image'],
            'message'       => $message,
        ]);
        break;


    // ── Action inconnue ───────────────────────────────────────────────────────
    default:
        reponse(false, [], "Action inconnue : \"$action\". Actions disponibles : get_personnages, verifier.");
}