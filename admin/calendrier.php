<?php
require_once "../conn.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Déterminer le mois à afficher (par défaut le mois en cours)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$start_date = sprintf("%04d-%02d-01", $year, $month);
$end_date = sprintf("%04d-%02d-%02d", $year, $month, $days_in_month);

// Récupérer toutes les chambres
$rooms = [];
$res_rooms = $conn->query("SELECT id, room_number, type FROM rooms ORDER BY room_number ASC");
while ($r = $res_rooms->fetch_assoc()) {
    $rooms[] = $r;
}

// Récupérer les réservations qui chevauchent ce mois
$sql = "
    SELECT r.id, r.room_id, r.client_name, r.check_in, r.check_out, r.status
    FROM reservations r
    WHERE r.status != 'cancelled'
    AND r.check_in <= ? AND r.check_out >= ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $end_date, $start_date);
$stmt->execute();
$reservations_result = $stmt->get_result();

$reservations = [];
while ($r = $reservations_result->fetch_assoc()) {
    $reservations[] = $r;
}
$stmt->close();

$month_name = strftime("%B", mktime(0, 0, 0, $month, 1, $year));
$month_name_fr = [
    1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin',
    7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'
][$month];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Calendrier des Réservations</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .calendar-container {
            overflow-x: auto;
            background: rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .calendar-table {
            border-collapse: collapse;
            width: 100%;
            min-width: 1200px;
            color: #ece4d2;
        }
        .calendar-table th, .calendar-table td {
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            height: 40px;
            position: relative;
        }
        .calendar-table th {
            background: rgba(0,0,0,0.3);
            font-size: 0.85rem;
            padding: 5px;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .calendar-table th.room-col {
            position: sticky;
            left: 0;
            background: rgba(0,0,0,0.8);
            z-index: 3;
            width: 150px;
            text-align: left;
            padding-left: 10px;
        }
        .calendar-table td.room-col {
            position: sticky;
            left: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1;
            font-weight: bold;
            text-align: left;
            padding-left: 10px;
        }
        
        /* Les barres de réservation */
        .res-bar {
            position: absolute;
            top: 5px;
            bottom: 5px;
            border-radius: 4px;
            color: white;
            font-size: 0.75rem;
            line-height: 30px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 5px;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            text-align: left;
        }
        .status-paid { background: linear-gradient(135deg, #34d399, #10b981); }
        .status-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .cal-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .cal-nav a {
            background: rgba(255,255,255,0.1);
            color: #d4af37;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid #d4af37;
            transition: 0.3s;
        }
        .cal-nav a:hover {
            background: #d4af37;
            color: #111;
        }
    </style>
</head>
<body>

<header>
    <h1>Calendrier Interactif</h1>
    <p>Vue d'ensemble des réservations</p>
    <div>
        <a class="logout-link" href="index.php">Retour Admin</a>
        <a class="logout-link" href="../logout.php">Déconnexion</a>
    </div>
</header>

<section class="container" style="max-width: 1400px;">

    <div class="cal-nav">
        <a href="calendrier.php?month=<?= $month-1 ?>&year=<?= $month == 1 ? $year-1 : $year ?>">❮ Mois précédent</a>
        <h2><?= $month_name_fr ?> <?= $year ?></h2>
        <a href="calendrier.php?month=<?= $month+1 ?>&year=<?= $month == 12 ? $year+1 : $year ?>">Mois suivant ❯</a>
    </div>

    <div class="calendar-container">
        <table class="calendar-table">
            <thead>
                <tr>
                    <th class="room-col">Chambres</th>
                    <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                        <th><?= $d ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td class="room-col">
                            Chambre <?= htmlspecialchars($room['room_number']) ?><br>
                            <span style="font-size:0.75rem; font-weight:normal; color:#aaa;"><?= htmlspecialchars($room['type']) ?></span>
                        </td>
                        
                        <?php
                        // Trouver les réservations pour cette chambre qui tombent dans ce mois
                        $room_res = array_filter($reservations, function($r) use ($room) {
                            return $r['room_id'] == $room['id'];
                        });
                        
                        for ($d = 1; $d <= $days_in_month; $d++) {
                            echo "<td></td>";
                        }
                        
                        // Dessiner les barres absolues
                        foreach ($room_res as $res) {
                            $res_start = strtotime($res['check_in']);
                            $res_end = strtotime($res['check_out']);
                            
                            $month_start = strtotime($start_date);
                            $month_end = strtotime($end_date);
                            
                            // Ignorer si ça ne touche pas du tout ce mois
                            if ($res_end < $month_start || $res_start > $month_end) continue;
                            
                            // Calculer les colonnes de début et de fin dans ce mois
                            $col_start = 1;
                            if ($res_start > $month_start) {
                                $col_start = (int)date('j', $res_start);
                            }
                            
                            $col_end = $days_in_month;
                            if ($res_end < $month_end) {
                                $col_end = (int)date('j', $res_end);
                            }
                            
                            $duration = $col_end - $col_start;
                            if ($duration <= 0) $duration = 1; // Minimum visuel (bien que le check_out soit le matin, on montre le chevauchement)
                            
                            // Pour placer la barre en absolu sur la grille des <td>
                            // On suppose que la col "Chambre" fait 150px (plus le padding), et on calcule en pourcentage le reste
                            // En CSS pur, c'est délicat car les td n'ont pas la position relative globale.
                            // Mais comme on a mis `position:relative` sur le `td`, on peut injecter la barre dans le <td> de départ
                            // et lui donner une largeur = 100% * duration + (duration-1)*borders.
                            ?>
                            <script>
                                // Un petit hack JS pour placer la barre après le chargement du DOM si on ne veut pas se casser la tête avec le CSS absolu depuis le tr
                            </script>
                            <?php
                        }
                        ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Script pour placer les barres au bon endroit -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('.calendar-table');
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            
            const reservations = <?= json_encode($reservations) ?>;
            const startMonthStr = "<?= $start_date ?>";
            const endMonthStr = "<?= $end_date ?>";
            const startMonthTime = new Date(startMonthStr).getTime();
            const endMonthTime = new Date(endMonthStr).getTime();
            const daysInMonth = <?= $days_in_month ?>;
            
            // On map les room_id vers les index de lignes
            const roomMap = {};
            <?php foreach ($rooms as $i => $room): ?>
                roomMap[<?= $room['id'] ?>] = <?= $i ?>;
            <?php endforeach; ?>
            
            reservations.forEach(res => {
                const rowIndex = roomMap[res.room_id];
                if (rowIndex === undefined) return;
                
                const tr = rows[rowIndex];
                if (!tr) return;
                
                const resStart = new Date(res.check_in).getTime();
                const resEnd = new Date(res.check_out).getTime();
                
                // Limiter au mois actuel
                let dStart = new Date(res.check_in);
                let dEnd = new Date(res.check_out);
                
                if (resEnd < startMonthTime || resStart > endMonthTime) return;
                
                let colStart = 1;
                if (resStart > startMonthTime) {
                    colStart = dStart.getDate();
                }
                
                let colEnd = daysInMonth;
                if (resEnd < endMonthTime) {
                    colEnd = dEnd.getDate();
                }
                
                let durationDays = colEnd - colStart;
                if (durationDays < 1) return; // Départ le 1er du mois
                
                // Le td de départ
                const tdStart = tr.cells[colStart];
                if (!tdStart) return;
                
                const bar = document.createElement('div');
                bar.className = 'res-bar status-' + res.status;
                bar.innerHTML = res.client_name;
                bar.title = res.client_name + " (" + res.check_in + " -> " + res.check_out + ")";
                
                // Largeur : 100% du td * durée
                // Plus la taille de la bordure (approximative)
                bar.style.width = `calc(${durationDays * 100}% + ${durationDays - 1}px)`;
                
                tdStart.appendChild(bar);
            });
        });
    </script>
</section>

</body>
</html>
