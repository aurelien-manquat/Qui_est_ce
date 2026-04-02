<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Qui est-ce ?</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Qui est-ce ?</h1>

<div class="container">

    <div class="gauche">
        <table>
            <tr>
            <?php
            for ($i = 0; $i < 16; $i++) {

                echo "<td class='carte'>";
                echo "<img src='pictures/$i.jpg'>";
                echo "</td>";

                if (($i + 1) % 4 == 0) {
                    echo "</tr><tr>";
                }
            }
            ?>
            </tr>
        </table>
    </div>

    
    <div class="droite">
        <form method="POST" action="traitement.php">

        <?php
        $questions = [
            "A-t-il des lunettes ?",
            "A-t-il une moustache ?",
            "A-t-il un chapeau ?",
            "A-t-il des cheveux ?",
            "A-t-il une boucle d'oreille ?",
            "A-t-il une barbe ?",
            "A-t-il un noeud papillon ?"
        ];

        for ($i = 0; $i < 7; $i++) {
            echo "<p>";
            echo ($i+1) . ". " . $questions[$i] . "<br>";
            echo "<input type='radio' name='q$i' value='1' required> Oui ";
            echo "<input type='radio' name='q$i' value='0'> Non ";
            echo "</p>";
        }
        ?>

        <input type="submit" value="Trouver le personnage">

        </form>
    </div>

</div>

</body>
</html>