<?php

// For norwegian days 
setlocale(LC_ALL, 'nb_NO.UTF-8');

// Number of months forward to see timeline, including current month.
$num_months=4;

// Page header
$page_header = "Header text";

// Replace with your API key
$apiKey = '';

// Replace with the ID of the schedule for which you want to retrieve the timeline
$scheduleId = '';

// Set up the URL for the API request
$schedule_url = "https://api.opsgenie.com/v2/schedules/$scheduleId/timeline?intervalUnit=months";
$user_url = "https://api.opsgenie.com/v2/users";

// Set up the headers for the API request
$headers = array(
    'Content-Type: application/json',
    'Authorization: GenieKey ' . $apiKey
);


function get_rotations($periods_array, $url, $headers) {

    // Set up the CURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the CURL request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        // Decode the response from JSON format to a PHP array
        $timeline = json_decode($response, true);

        #    print_r($timeline['data']['finalTimeline']);

        // Output the start date, end date, and name of each item in the timeline
        foreach ($timeline['data']['finalTimeline']['rotations'] as $rotation) {
            #        echo "test:\n";
            #        print_r($rotation);
            foreach ($rotation['periods'] as $period) {
                #        print_r($period);

                $periods_array[] = array(
                    'startDate' => strtotime($period['startDate']),
                    'endDate'   => strtotime($period['endDate']),
                    'type'      => $period['type'],
                    'Name'      => $period['recipient']['name']
                );
            }
        }
    }
    // Close the CURL request
    curl_close($ch);

    return $periods_array;

}

function get_users($url, $headers) {

    // Set up the CURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the CURL request
    $response = curl_exec($ch);

    #var_dump($response);

    // Check for errors
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {

        $users = json_decode($response, true);

        foreach ($users['data'] as $user) {

            $user_array[] = array(
                'username' => $user['username'],
                'fullName' => $user['fullName']
            );
        }
    }
    // Close the CURL request
    curl_close($ch);

    return $user_array;

}

# Get users
$user_array = array();
$user_array = get_users($user_url, $headers);

$num_month_inc_prev = $num_months + 1;
$prev_month = date('c',strtotime('first day of -1 month'));
$prev_month = str_replace('+', '%2B', $prev_month );
$url_interval_addon = "&date=$prev_month&interval=$num_month_inc_prev";
$url = $schedule_url . $url_interval_addon;

# Get rotations for current month
$periods_array = array();
$periods_array = get_rotations($periods_array, $url, $headers);

#print_r($periods_array);

# Sort array byt start date
usort($periods_array, function($a, $b) {
    return $a['startDate'] - $b['startDate'];
});

# OpsGenie devides a rotation in two when it spans into a new month. This joins that rotation together again
$previous = null;
foreach ($periods_array as $key => $current) {
    if ($previous != null &&
        $previous['endDate'] == $current['startDate'] &&
        $previous['type'] == $current['type'] &&
        $previous['Name'] == $current['Name']
    ) {
        $periods_array[$key-1]['endDate'] = $current['endDate'];
        unset($periods_array[$key]);
    }
    $previous = $current;
}

?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta charset="UTF-8">
<title><?php echo $page_header; ?></title>
<style>
table, th, td {
border: 1px solid;
padding: 5px;
}
table {
    border-collapse: collapse;
}
</style>
</head>
<body>
<?php

echo "<h1>$page_header</h1>";
echo '<div>All data are gathered from OpsGenie.</div>';

echo '<h2>Vaktliste</h2>';
echo '<div>The list shows the which users are on-call for the current and the next ' . ($num_months - 1) . ' months.</div>';
echo "<table>";
echo '<tr>';
echo '<th>Name</th><th>From</th><th>To</th><th>Misc</th>';
echo '</tr>';
foreach ($periods_array as $rotation) {
    if ($rotation['type'] <> 'historical') {
        echo "<tr>";
        echo '<td>';
        foreach ($user_array as $user) {
            if ($user['username'] === $rotation['Name']) {
                // Match found, print the corresponding full name
                echo $user['fullName'] . ' (' .$rotation['Name'] . ")";
                break; // Exit the loop since we found a match
            }
        }
        echo "</td>\n";
        echo '<td>' . ucfirst(strftime("%A %e/%m %Y, kl %H:%M", $rotation['startDate'])) . '</td>';
        echo '<td>' . ucfirst(strftime("%A %e/%m %Y, kl %H:%M", $rotation['endDate'])) . '</td>';

        echo '<td>';
        if ( ( time() >= $rotation['startDate'])  &&  ( time() <= $rotation['endDate'] ) ) {
            echo 'On-call now.';
        }
        elseif ( $rotation['type'] == "override") {
            echo "Override.";
        }
        else {
            echo "&nbsp;";
        }
        echo '</td>';
        echo '</tr>';
    }
}
echo '</table>';

usort($periods_array, function($a, $b) {
    return $b['startDate'] - $a['startDate'];
});

echo '<h2>Previous On-call</h2>';
echo '<div>The list shows which users that were on-call this and previous month.</div>';
echo "<table>";
echo '<tr>';
echo '<th>Name</th><th>From</th><th>To</th><th>Misc</th>';
echo '</tr>';
foreach ($periods_array as $rotation) {
    if ($rotation['type'] == 'historical') {
        echo "<tr>";
        echo '<td>';
        foreach ($user_array as $user) {
            if ($user['username'] === $rotation['Name']) {
                // Match found, print the corresponding full name
                echo $user['fullName'] . ' (' .$rotation['Name'] . ")";
                break; // Exit the loop since we found a match
            }
        }
        echo "</td>\n";
        echo '<td>' . ucfirst(strftime("%A %e/%m %Y, kl %H:%M", $rotation['startDate'])) . '</td>';
        echo '<td>' . ucfirst(strftime("%A %e/%m %Y, kl %H:%M", $rotation['endDate'])) . '</td>';

        echo '<td>';
        if ( ( time() >= $rotation['startDate'])  &&  ( time() <= $rotation['endDate'] ) ) {
            echo 'On-call now.';
        }
        elseif ( $rotation['type'] == "override") {
            echo "Override.";
        }
        else {
            echo "&nbsp;";
        }
        echo '</td>';
        echo '</tr>';
    }
}
echo '</table>';
echo '</body>';
echo '</html>';
