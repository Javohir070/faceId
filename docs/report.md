
<?php
    require("includes/db.php");
    require("includes/functions.php");
    require("includes/lang.php");
    
    // Debug mode: show all PHP errors directly on this page.
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if(empty($_SESSION['name'])) header('location: login.php');
    if($_SESSION["report_rights"] != 1) header('location: login.php');

    $page = "reports";

    $alerts = [];

    $employee = "";
    $group = "";
    $device = "";
    $category = "";
    $schedule = "";
    $type = "";
    /* Пустой период = все записи по всем таблицам reports*; даты применяются только если заданы в форме (как в MdParkingweb/reports.php). */
    $dt_from = "";
    $dt_to = "";
    $department = $_SESSION["department"];

    if(isset($_GET["employee"]) && !empty($_GET["employee"])) $employee = mysqli_real_escape_string($con, $_GET["employee"]);
    if(isset($_GET["group"]) && !empty($_GET["group"])) $group = mysqli_real_escape_string($con, $_GET["group"]);
    if(isset($_GET["device"]) && !empty($_GET["device"])) $device = mysqli_real_escape_string($con, $_GET["device"]);
    if(isset($_GET["category"]) && !empty($_GET["category"])) $category = mysqli_real_escape_string($con, $_GET["category"]);
    if(isset($_GET["schedule"]) && !empty($_GET["schedule"])) $schedule = mysqli_real_escape_string($con, $_GET["schedule"]);
    if(isset($_GET["type"]) && !empty($_GET["type"])) $type = mysqli_real_escape_string($con, $_GET["type"]);
    if(isset($_GET["dt_from"]) && !empty($_GET["dt_from"])) $dt_from = mysqli_real_escape_string($con, $_GET["dt_from"]);
    if(isset($_GET["dt_to"]) && !empty($_GET["dt_to"])) $dt_to = mysqli_real_escape_string($con, $_GET["dt_to"]);

    $plate = isset($_GET["plate"]) ? trim($_GET["plate"]) : "";
    $plate_emp_ids = [];
    if($plate !== ""){
        $park = parking_db_connect();
        if($park){
            $plate_emp_ids = parking_employee_ids_by_plate($park, $plate);
            mysqli_close($park);
        }
    }

    $reports_per_page = 30;
    $reports_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $reports_offset = ($reports_page - 1) * $reports_per_page;
    $reports_total = 0;

    function reports_page_url($page){
        $params = $_GET;
        if($page <= 1) unset($params['p']);
        else $params['p'] = $page;
        $qs = http_build_query($params);
        return 'reports.php'.($qs !== '' ? '?'.$qs : '');
    }

    function reports_count_from_sql($con, $sql){
        $result = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM ($sql) AS report_count");
        if(!$result) return 0;
        $row = mysqli_fetch_assoc($result);
        return (int)$row['cnt'];
    }

    function reports_paged_sql($sql, $offset, $limit){
        return "SELECT * FROM ($sql) AS report_page LIMIT ".(int)$offset.", ".(int)$limit;
    }

    function reports_pagination_html($total, $current_page, $per_page){
        if($total <= $per_page) return '';

        $total_pages = (int)ceil($total / $per_page);
        $current_page = max(1, min($current_page, $total_pages));
        $from = ($current_page - 1) * $per_page + 1;
        $to = min($current_page * $per_page, $total);

        $html = '<div class="reports-pagination">';
        $html .= '<div class="reports-pagination-info">Записи '.$from.'–'.$to.' из '.$total.'</div>';
        $html .= '<nav class="reports-pagination-nav" aria-label="Pagination"><ul class="reports-pagination-list">';

        $html .= reports_pagination_item(1, '«', $current_page <= 1, $current_page === 1);
        $html .= reports_pagination_item($current_page - 1, '‹', $current_page <= 1);

        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);

        if($start > 1){
            $html .= reports_pagination_item(1, '1', false, $current_page === 1);
            if($start > 2) $html .= '<li class="reports-pagination-ellipsis"><span>…</span></li>';
        }

        for($i = $start; $i <= $end; $i++){
            $html .= reports_pagination_item($i, (string)$i, false, $i === $current_page);
        }

        if($end < $total_pages){
            if($end < $total_pages - 1) $html .= '<li class="reports-pagination-ellipsis"><span>…</span></li>';
            $html .= reports_pagination_item($total_pages, (string)$total_pages, false, $current_page === $total_pages);
        }

        $html .= reports_pagination_item($current_page + 1, '›', $current_page >= $total_pages);
        $html .= reports_pagination_item($total_pages, '»', $current_page >= $total_pages, $current_page === $total_pages);
        $html .= '</ul></nav></div>';

        return $html;
    }

    function reports_pagination_item($page, $label, $disabled = false, $active = false){
        $classes = ['reports-pagination-link'];
        if($disabled) $classes[] = 'disabled';
        if($active) $classes[] = 'active';
        $class_attr = implode(' ', $classes);

        if($disabled){
            return '<li><span class="'.htmlspecialchars($class_attr).'">'.htmlspecialchars($label).'</span></li>';
        }

        return '<li><a class="'.htmlspecialchars($class_attr).'" href="'.htmlspecialchars(reports_page_url($page)).'">'.htmlspecialchars($label).'</a></li>';
    }

    function getGroups($parent){
        global $con, $department;
        $_groups = [];
        $sql = "SELECT id, name FROM groups WHERE parent = $parent";
        if($department) $sql .= " AND department = '$department'";
        $rows = mysqli_query($con, $sql);
        while ($row = mysqli_fetch_assoc($rows)){
            $_group = ["id" => $row["id"], "title" => $row["name"]];
            $sub = getGroups($row["id"]);
            if(!empty($sub)) $_group["subs"] = $sub;
            $_groups[] = $_group;
        }
        return $_groups;
    }

    $_groups = getGroups(0);
?>

<!DOCTYPE html>
<html>
    <head>

        <?php include("widgets/assets.php"); ?>

    </head>
    <body>
        <div class="wrapper">

            <?php include("widgets/sidebar.php"); ?>

            <!-- Page Content Holder -->
            <div id="content">

                <?php include("widgets/nav.php"); ?>

                <?php
                    if(count($alerts) > 0){
                        foreach($alerts as $alert) echo $alert;
                    }
                ?>

                <div class="panel panel-default">
                    <div class="panel-heading"><?php echo $lang["reports"] ?></div>
                    <div class="panel-body">
                        <form style="margin-bottom: 10px;" method="get">
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="employee"><?php echo $lang["employee"]; ?>:</label>
                                        <select class="fstdropdown-select form-control" id="employee" name="employee">
                                            <option value="">-- <?php echo $lang["select_employee"]; ?> --</option>
                                            <?php
                                                $sql = "SELECT id, name FROM employees";
                                                $data = mysqli_query($con, $sql);
                                                while ($row = mysqli_fetch_assoc($data)){
                                                    $selected = ($employee == $row["id"]) ? 'selected' : '';
                                                    echo '<option value="'.$row["id"].'" '.$selected.'>'.$row["name"].'</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="group"><?php echo $lang["group"]; ?>:</label>
                                        <input type="text" id="comboTree" placeholder="<?php echo $lang["select_group"]; ?>" autocomplete="off"/>
                                        <input type="hidden" name="group" value="<?php echo $group; ?>">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="device"><?php echo $lang["device"]; ?>:</label>
                                        <select class="fstdropdown-select form-control" id="device" name="device">
                                            <option value="">-- <?php echo $lang["select_device"]; ?> --</option>
                                            <?php
                                                $sql = "SELECT id, name FROM devices";
                                                $rows = mysqli_query($con, $sql);
                                                while ($row = mysqli_fetch_assoc($rows)){
                                                    $selected = ($device == $row["id"]) ? 'selected' : '';
                                                    echo '<option value="'.$row["id"].'" '.$selected.'>'.$row["name"].'</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="category"><?php echo $lang["category"]; ?>:</label>
                                        <select class="fstdropdown-select form-control" id="category" name="category">
                                            <option value="">-- <?php echo $lang["select_category"]; ?> --</option>
                                            <?php
                                                $sql = "SELECT id, name FROM categories";
                                                $rows = mysqli_query($con, $sql);
                                                while ($row = mysqli_fetch_assoc($rows)){
                                                    $selected = ($category == $row["id"]) ? 'selected' : '';
                                                    echo '<option value="'.$row["id"].'" '.$selected.'>'.$row["name"].'</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="control-label" for="plate"><?php echo $lang["number_plate"]; ?>:</label>
                                        <input type="text" name="plate" id="plate" class="form-control" value="<?php echo htmlspecialchars($plate); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="schedule"><?php echo $lang["schedule"]; ?>:</label>
                                        <select class="fstdropdown-select form-control" id="schedule" name="schedule">
                                            <option value="">-- <?php echo $lang["select_schedule"]; ?> --</option>
                                            <?php
                                                $sql = "SELECT id, name FROM schedules";
                                                $rows = mysqli_query($con, $sql);
                                                while ($row = mysqli_fetch_assoc($rows)){
                                                    $selected = ($schedule == $row["id"]) ? 'selected' : '';
                                                    echo '<option value="'.$row["id"].'" '.$selected.'>'.$row["name"].'</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="type">Type:</label>
                                        <select class="fstdropdown-select form-control" data-searchdisable="true" id="type" name="type">
                                            <option value="">-- <?php echo $lang["select_type"] ?> --</option>
                                            <option value="1" <?php if($type == 1) echo 'selected'; ?>><?php echo $lang["check_in"] ?></option>
                                            <option value="2" <?php if($type == 2) echo 'selected'; ?>><?php echo $lang["check_out"] ?></option>
                                            <option value="3" <?php if($type == 3) echo 'selected'; ?>><?php echo $lang["came_late"] ?></option>
                                            <option value="4" <?php if($type == 4) echo 'selected'; ?>><?php echo $lang["left_early"] ?></option>
                                            <option value="5" <?php if($type == 5) echo 'selected'; ?>><?php echo $lang["present"] ?></option>
                                            <option value="6" <?php if($type == 6) echo 'selected'; ?>><?php echo $lang["absent"] ?></option>
                                            <option value="7" <?php if($type == 7) echo 'selected'; ?>><?php echo $lang["work_time"] ?></option>
                                            <option value="8" <?php if($type == 8) echo 'selected'; ?>><?php echo $lang["customized"] ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="date_from"><?php echo $lang["datetime_from"] ?>:</label>
                                        <div class="input-group date" id="dp1">
                                            <input name="dt_from" type="text" class="form-control" value="<?php echo $dt_from ?>">
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="control-label" for="dp2"><?php echo $lang["datetime_to"] ?>:</label>
                                        <div class="input-group date" id="dp2">
                                            <input name="dt_to" type="text" class="form-control" value="<?php echo $dt_to ?>">
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="overflow: auto">
                                <div id="buttons"></div>
                                <button type="submit" class="btn btn-primary pull-right"><?php echo $lang["generate"] ?></button>
                            </div> 
                        </form>
                        <div class="reports-datatable">
                        <table class="display" id="tb">
                            <?php
                                $tablesUnion = biocontrol_reports_build_union($con, $db_name, $dt_from, $dt_to);
                                $cal_bounds = biocontrol_reports_calendar_bounds($con, $tablesUnion, $dt_from, $dt_to);
                                $cal_date_from = mysqli_real_escape_string($con, $cal_bounds['from']);
                                $cal_date_to = mysqli_real_escape_string($con, $cal_bounds['to']);

                                //filter
                                $cond = "AND true";
                                if($employee) $cond .= " AND e.id = '$employee'";
                                if($group) $cond .= " AND e.group_id IN ($group)";
                                if($schedule) $cond .= " AND e.id IN (SELECT emp.id FROM employees AS emp WHERE emp.schedule = $schedule)";
                                if($dt_from) $cond .= " AND r.dt >= '$dt_from'";
                                if($dt_to) $cond .= " AND r.dt <= '$dt_to'";
                                if($department) $cond .= " AND e.department = '$department'";
                                $cond .= " AND CONCAT(DAY(r.dt), '.', MONTH(r.dt)) NOT IN (SELECT CONCAT(day, '.', month) FROM holidays)";
                                if($plate !== ""){
                                    if(empty($plate_emp_ids)) $cond .= " AND e.id = -1";
                                    else $cond .= " AND e.id IN (".implode(",", array_map("intval", $plate_emp_ids)).")";
                                }

                                // report
                                if($type == 3){ // came late
                                    if($device) $cond .= " AND r.dev_id = '$device'";
                                    if($category) $cond .= " AND r.dev_id IN (SELECT dev.id FROM devices AS dev WHERE dev.category = $category)";
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["date"].'</th>
                                            <th>'.$lang["time"].'</th>
                                            <th>'.$lang["late"].'</th>
                                            <th>'.$lang["reason"].'</th>
                                        </tr></thead><tbody>';
                                    $sql = "SELECT 
                                                e.id, 
                                                e.name, 
                                                g.name AS _group,
                                                e.position,
                                                e.rate,
                                                DATE_FORMAT(MIN(r.dt), '%Y-%m-%d') AS date,
                                                DATE_FORMAT(MIN(r.dt),'%H:%i:%s') AS time, 
                                                s.enter_time,
                                                n.note,
                                                CASE
                                                    WHEN s.enter_time > s.exit_time 
                                                    THEN TIMEDIFF(DATE_FORMAT(MIN(r.shift_dt),'%H:%i:%s'), s.shift_enter_time)
                                                    ELSE TIMEDIFF(DATE_FORMAT(MIN(r.dt),'%H:%i:%s'), s.enter_time) 
                                                END AS late 
                                            FROM 
                                                (
                                                    SELECT
                                                        id,
                                                        emp_id,
                                                        dev_id,
                                                        type,
                                                        dt,
                                                        ADDTIME(dt, '-12:00:00') AS shift_dt
                                                    FROM
                                                        ($tablesUnion) AS reports
                                                ) AS r
                                                JOIN employees AS e ON r.emp_id = e.id
                                                JOIN (
                                                    SELECT
                                                        id,
                                                        name,
                                                        enter_time,
                                                        exit_time,
                                                        ADDTIME(enter_time, '-12:00:00') AS shift_enter_time,
                                                        ADDTIME(exit_time, '-12:00:00') AS shift_exit_time
                                                    FROM
                                                        schedules
                                                ) AS s ON e.schedule = s.id
                                                LEFT JOIN notes AS n ON n.employee = e.id AND n.type = 1 AND DATE(r.dt) = n.dt
                                                LEFT JOIN groups AS g ON e.group_id = g.id
                                            WHERE 
                                                r.type = 1
                                                $cond
                                            GROUP BY 
                                                r.emp_id, 
                                                CASE
                                                    WHEN s.enter_time > s.exit_time
                                                    THEN DATE_FORMAT(r.shift_dt, '%Y-%m-%d')
                                                    ELSE DATE_FORMAT(r.dt, '%Y-%m-%d')
                                                END
                                            HAVING
                                                time > s.enter_time";
                                    $reports_total = reports_count_from_sql($con, $sql);
                                    $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $reports_per_page));
                                    $count = $reports_offset;
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $count++;
                                        echo '<tr>
                                                <td>'.$count.'</td>
                                                <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row['name'].'</a></td>
                                                <td>'.$row['_group'].'</td>
                                                <td>'.$row['position'].'</td>
                                                <td>'.$row['rate'].'</td>
                                                <td>'.$row['date'].'</td>
                                                <td>'.$row['time'].'</td>
                                                <td>'.$row['late'].'</td>
                                                <td>'.$row['note'].'</td>
                                            </tr>';
                                    }
                                    echo '</tbody>';
                                }else if($type == 4){ // left early
                                    if($device) $cond .= " AND r.dev_id = '$device'";
                                    if($category) $cond .= " AND r.dev_id IN (SELECT dev.id FROM devices AS dev WHERE dev.category = $category)";
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["date"].'</th>
                                            <th>'.$lang["time"].'</th>
                                            <th>'.$lang["early"].'</th>
                                            <th>'.$lang["reason"].'</th>
                                        </tr></thead><tbody>';
                                    $sql = "SELECT 
                                                e.id,
                                                e.name, 
                                                g.name AS _group,
                                                e.position,
                                                e.rate,
                                                DATE_FORMAT(MAX(r.dt), '%Y-%m-%d') AS date,
                                                DATE_FORMAT(MAX(r.dt),'%H:%i:%s') AS time, 
                                                s.exit_time,
                                                n.note,
                                                CASE
                                                    WHEN s.enter_time > s.exit_time 
                                                    THEN TIMEDIFF(s.shift_exit_time, DATE_FORMAT(MAX(r.shift_dt),'%H:%i:%s'))
                                                    ELSE TIMEDIFF(s.exit_time, DATE_FORMAT(MAX(r.dt),'%H:%i:%s')) 
                                                END AS early 
                                            FROM 
                                                (
                                                    SELECT
                                                        id,
                                                        emp_id,
                                                        dev_id,
                                                        type,
                                                        dt,
                                                        ADDTIME(dt, '-12:00:00') AS shift_dt
                                                    FROM
                                                        ($tablesUnion) AS reports
                                                ) AS r
                                                JOIN employees AS e ON r.emp_id = e.id
                                                JOIN (
                                                    SELECT
                                                        id,
                                                        name,
                                                        enter_time,
                                                        exit_time,
                                                        ADDTIME(enter_time, '-12:00:00') AS shift_enter_time,
                                                        ADDTIME(exit_time, '-12:00:00') AS shift_exit_time
                                                    FROM
                                                        schedules
                                                ) AS s ON e.schedule = s.id
                                                LEFT JOIN notes AS n ON n.employee = e.id AND n.type = 2 AND DATE(r.dt) = n.dt
                                                LEFT JOIN groups AS g ON e.group_id = g.id
                                            WHERE
                                                r.type = 2
                                                $cond
                                            GROUP BY 
                                                r.emp_id, 
                                                CASE
                                                    WHEN s.enter_time > s.exit_time
                                                    THEN DATE_FORMAT(r.shift_dt, '%Y-%m-%d')
                                                    ELSE DATE_FORMAT(r.dt, '%Y-%m-%d')
                                                END
                                            HAVING
                                                time < s.exit_time";
                                    $reports_total = reports_count_from_sql($con, $sql);
                                    $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $reports_per_page));
                                    echo mysqli_error($con);
                                    $count = $reports_offset;
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $count++;
                                        echo '<tr>
                                                <td>'.$count.'</td>
                                                <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row['name'].'</a></td>
                                                <td>'.$row['_group'].'</td>
                                                <td>'.$row["position"].'</td>
                                                <td>'.$row["rate"].'</td>
                                                <td>'.$row['date'].'</td>
                                                <td>'.$row['time'].'</td>
                                                <td>'.$row['early'].'</td>
                                                <td>'.$row['note'].'</td>
                                            </tr>';
                                    }
                                    echo '</tbody>';
                                }else if($type == 5){ // present
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["date"].'</th>
                                        </tr></thead><tbody>';
                                    $sql = "SELECT e.id, e.name, g.name AS _group, e.position, e.rate, r.dt AS d FROM employees AS e LEFT JOIN groups AS g ON e.group_id = g.id,
                                            (SELECT start_date + INTERVAL num DAY dt FROM
                                            (SELECT tth*10000+th*1000+h*100+t*10+u num FROM
                                            (SELECT 0 tth UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TTHN,
                                            (SELECT 0 th UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) THN,
                                            (SELECT 0 h UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) HN,
                                            (SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TN,
                                            (SELECT 0 u UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) UN) A,
                                            (SELECT DATE('$cal_date_from') start_date) B,
                                            (SELECT DATE('$cal_date_to') end_date) C
                                            WHERE start_date + INTERVAL num DAY <= end_date
                                            ORDER BY num) AS r
                                            WHERE e.id IN (SELECT emp_id FROM ($tablesUnion) AS reports WHERE DATE(dt) = r.dt) $cond
                                            ORDER BY r.dt, e.name";
                                    $reports_total = reports_count_from_sql($con, $sql);
                                    $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $reports_per_page));
                                    $count = $reports_offset;
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $count++;
                                        echo '<tr>
                                                <td>'.$count.'</td>
                                                <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row['name'].'</a></td>
                                                <td>'.$row['_group'].'</td>
                                                <td>'.$row["position"].'</td>
                                                <td>'.$row["rate"].'</td>
                                                <td>'.$row['d'].'</td>
                                            </tr>';
                                    }
                                    echo '</tbody>';
                                }else if($type == 6){ // absent
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["date"].'</th>
                                            <th>'.$lang["reason"].'</th>
                                        </tr></thead><tbody>';
                                    $sql = "SELECT e.id, e.name, g.name AS _group, e.position, e.rate, r.dt AS d, n.note FROM employees AS e LEFT JOIN groups AS g ON e.group_id = g.id
                                            JOIN (SELECT start_date + INTERVAL num DAY dt FROM
                                            (SELECT tth*10000+th*1000+h*100+t*10+u num FROM
                                            (SELECT 0 tth UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TTHN,
                                            (SELECT 0 th UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) THN,
                                            (SELECT 0 h UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) HN,
                                            (SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) TN,
                                            (SELECT 0 u UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) UN) A,
                                            (SELECT DATE('$cal_date_from') start_date) B,
                                            (SELECT DATE('$cal_date_to') end_date) C
                                            WHERE start_date + INTERVAL num DAY <= end_date
                                            ORDER BY num) AS r ON true
                                            LEFT JOIN notes AS n ON n.employee = e.id AND n.type = 3 AND DATE(r.dt) = n.dt
                                            WHERE e.id NOT IN (SELECT emp_id FROM ($tablesUnion) AS reports WHERE DATE(dt) = r.dt) $cond
                                            ORDER BY r.dt, e.name";
                                    $reports_total = reports_count_from_sql($con, $sql);
                                    $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $reports_per_page));
                                    echo mysqli_error($con);
                                    $count = $reports_offset;
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $count++;
                                        echo '<tr>
                                                <td>'.$count.'</td>
                                                <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row['name'].'</a></td>
                                                <td>'.$row['_group'].'</td>
                                                <td>'.$row["position"].'</td>
                                                <td>'.$row["rate"].'</td>
                                                <td>'.$row['d'].'</td>
                                                <td>'.$row['note'].'</td>
                                            </tr>';
                                    }
                                    echo '</tbody>';
                                }else if($type == 7){ // work time
                                    if($device) $cond .= " AND r.dev_id = '$device'";
                                    if($category) $cond .= " AND r.dev_id IN (SELECT dev.id FROM devices AS dev WHERE dev.category = $category)";
                                    $wt_from = $cal_bounds['from'];
                                    $wt_to = $cal_bounds['to'];
                                    if($wt_from != $wt_to) $dates = dateRange($wt_from, $wt_to);
                                    else $dates = [$wt_from];
                                    echo '<thead><tr><th>№</th><th>'.$lang["name"].'</th><th>'.$lang["group"].'</th><th>'.$lang["position"].'</th><th>'.$lang["rate"].'</th>';
                                    for($i = 0; $i < count($dates); $i++){
                                        $d = $dates[$i];
                                        echo '<th>'.$d.'</th>';
                                    }
                                    echo '<th>'.$lang["total"].'</th></tr></thead><tbody>';
                                    $sql = "SELECT 
                                                e.id,
                                                e.name AS name,
                                                g.name AS _group,
                                                e.position,
                                                e.rate,
                                                DATE_FORMAT(MIN(r.dt), '%Y-%m-%d') AS date,
                                                CASE
                                                    WHEN s.enter_time > s.exit_time 
                                                    THEN TIMEDIFF(MAX(CASE WHEN r.type = 2 THEN r.shift_dt END), MIN(CASE WHEN r.type = 1 THEN r.shift_dt END))
                                                    ELSE TIMEDIFF(MAX(CASE WHEN r.type = 2 THEN r.dt END), MIN(CASE WHEN r.type = 1 THEN r.dt END))
                                                END AS time 
                                            FROM 
                                                (
                                                    SELECT
                                                        id,
                                                        emp_id,
                                                        dev_id,
                                                        type,
                                                        dt,
                                                        ADDTIME(dt, '-12:00:00') AS shift_dt
                                                    FROM
                                                        ($tablesUnion) AS reports
                                                ) AS r,
                                                schedules AS s, 
                                                employees AS e 
                                                LEFT JOIN groups AS g ON e.group_id = g.id
                                            WHERE 
                                                r.emp_id = e.id AND
                                                e.schedule = s.id
                                                $cond
                                            GROUP BY 
                                                r.emp_id, 
                                                CASE
                                                    WHEN s.enter_time > s.exit_time
                                                    THEN CAST(r.shift_dt AS DATE)
                                                    ELSE CAST(r.dt AS DATE)
                                                END";
                                    $rows = mysqli_query($con, $sql);
                                    $arr = [];
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $arr[$row['id']]["name"] = $row["name"];
                                        $arr[$row['id']]["group"] = $row["_group"];
                                        $arr[$row['id']]["position"] = $row["position"];
                                        $arr[$row['id']]["rate"] = $row["rate"];
                                        $arr[$row['id']][$row['date']] = $row['time'];
                                    }
                                    $reports_total = count($arr);
                                    $arr_page = array_slice($arr, $reports_offset, $reports_per_page, true);
                                    if(count($arr_page) > 0){
                                        $count = $reports_offset;
                                        foreach ($arr_page as $key => $value){
                                            $count++;
                                            echo '<tr><td>'.$count.'</td><td>'.$value["name"].'</td><td>'.$value["group"].'</td><td>'.$value["position"].'</td><td>'.$value["rate"].'</td>';
                                            $t = strtotime('00:00:00');
                                            $total = 0;
                                            for($i = 0; $i < count($dates); $i++){
                                                if($value[$dates[$i]]) $total += strtotime($value[$dates[$i]]) - $t;
                                                $cell = $value[$dates[$i]] ? $value[$dates[$i]] : 0;
                                                echo '<td>'.$cell.'</td>';
                                            }
                                            echo '<td>'.sprintf('%02d:%02d:%02d', ($total / 3600), ($total / 60 % 60), $total % 60).'</td></tr>';
                                        }
                                    }
                                    echo '</tbody>';
                                }else if($type == 8){ // customized
                                    if($device) $cond .= " AND r.dev_id = '$device'";
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["date"].'</th>
                                            <th>'.$lang["check_in"].'</th>
                                            <th>'.$lang["check_out"].'</th>
                                        </tr></thead><tbody>';
                                    $sql = "SELECT 
                                                e.id, 
                                                e.name,
                                                g.name AS _group,
                                                e.position,
                                                e.rate,
                                                DATE_FORMAT(r.dt, '%Y-%m-%d') AS date,
                                                CASE
                                                    WHEN s.enter_time > s.exit_time 
                                                    THEN ADDTIME(MIN(CASE WHEN r.type = 1 THEN r.shift_dt END), '12:00:00')
                                                    ELSE MIN(CASE WHEN r.type = 1 THEN r.dt END)
                                                END AS check_in, 
                                                CASE
                                                    WHEN s.enter_time > s.exit_time 
                                                    THEN ADDTIME(MAX(CASE WHEN r.type = 2 THEN r.shift_dt END), '12:00:00') 
                                                    ELSE MAX(CASE WHEN r.type = 2 THEN r.dt END)
                                                END AS check_out 
                                            FROM 
                                                (
                                                    SELECT
                                                        id,
                                                        emp_id,
                                                        dev_id,
                                                        type,
                                                        dt,
                                                        ADDTIME(dt, '-12:00:00') AS shift_dt
                                                    FROM
                                                        ($tablesUnion) AS reports
                                                ) AS r,
                                                schedules AS s, 
                                                employees AS e 
                                                LEFT JOIN groups AS g ON e.group_id = g.id
                                            WHERE 
                                                r.emp_id = e.id AND
                                                e.schedule = s.id
                                                $cond 
                                            GROUP BY 
                                                r.emp_id, 
                                                CASE
                                                    WHEN s.enter_time > s.exit_time
                                                    THEN CAST(r.shift_dt AS DATE)
                                                    ELSE CAST(r.dt AS DATE)
                                                END";
                                    $reports_total = reports_count_from_sql($con, $sql);
                                    $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $reports_per_page));
                                    $count = $reports_offset;
                                    while($row = mysqli_fetch_assoc($rows)){
                                        $count++;
                                        echo '<tr>
                                                <td>'.$count.'</td>
                                                <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row['name'].'</a></td>
                                                <td>'.$row["_group"].'</td>
                                                <td>'.$row["position"].'</td>
                                                <td>'.$row["rate"].'</td>
                                                <td>'.$row['date'].'</td>
                                                <td>'.$row['check_in'].'</td>
                                                <td>'.$row['check_out'].'</td>
                                            </tr>';
                                    }
                                    echo '</tbody>';
                                }else{ // check in / out
                                    if($device) $cond .= " AND r.dev_id = '$device'";
                                    if($category) $cond .= " AND r.dev_id IN (SELECT dev.id FROM devices AS dev WHERE dev.category = $category)";
                                    echo '<thead><tr>
                                            <th>№</th>
                                            <th>'.$lang["name"].'</th>
                                            <th>'.$lang["group"].'</th>
                                            <th>'.$lang["position"].'</th>
                                            <th>'.$lang["rate"].'</th>
                                            <th>'.$lang["device"].'</th>
                                            <th>'.$lang["type"].'</th>
                                            <th>'.$lang["datetime"].'</th>
                                        </tr></thead><tbody>';
                                    if($type) $cond .= " AND r.type = '$type'";
                                    $sql = "SELECT 
                                                e.id, 
                                                e.name, 
                                                g.name AS _group,
                                                e.position,
                                                e.rate,
                                                d.name as device,
                                                r.type, 
                                                r.dt 
                                            FROM 
                                                ($tablesUnion) AS r,
                                                devices AS d,
                                                employees AS e
                                                LEFT JOIN groups AS g ON e.group_id = g.id
                                            WHERE 
                                                r.emp_id = e.id AND 
                                                r.dev_id = d.id 
                                                $cond 
                                            ORDER BY 
                                                r.dt DESC";
                                    $main_total = reports_count_from_sql($con, $sql);
                                    $parking_total = 0;
                                    $parking_cond = "";
                                    if($plate !== "" && !empty($plate_emp_ids)){
                                        $park = parking_db_connect();
                                        if($park){
                                            $plate_sql = mysqli_real_escape_string($park, $plate);
                                            $parking_cond = " AND r.plate LIKE '%$plate_sql%'";
                                            if($dt_from) $parking_cond .= " AND r.dt >= '".mysqli_real_escape_string($park, $dt_from)."'";
                                            if($dt_to) $parking_cond .= " AND r.dt <= '".mysqli_real_escape_string($park, $dt_to)."'";
                                            if($type) $parking_cond .= " AND r.type = '".(int)$type."'";
                                            $parking_count_sql = "SELECT COUNT(*) AS cnt
                                                                FROM reports r
                                                                LEFT JOIN plates p ON p.plate = r.plate
                                                                LEFT JOIN plate_employee_bindings b ON b.plate_id = p.id
                                                                WHERE b.employee_id IS NOT NULL
                                                                AND b.employee_id IN (".implode(",", array_map("intval", $plate_emp_ids)).")
                                                                $parking_cond";
                                            $parking_count_rows = mysqli_query($park, $parking_count_sql);
                                            if($parking_count_rows) $parking_total = (int)mysqli_fetch_assoc($parking_count_rows)['cnt'];
                                            mysqli_close($park);
                                        }
                                    }
                                    $reports_total = $main_total + $parking_total;
                                    $count = $reports_offset;
                                    $remaining = $reports_per_page;
                                    echo mysqli_error($con);

                                    if($reports_offset < $main_total && $remaining > 0){
                                        $main_limit = min($remaining, $main_total - $reports_offset);
                                        $rows = mysqli_query($con, reports_paged_sql($sql, $reports_offset, $main_limit));
                                        while($rows && ($row = mysqli_fetch_assoc($rows))){
                                            $count++;
                                            $remaining--;
                                            $type_label = ($row["type"] == 1) ? $lang["check_in"] : $lang["check_out"];
                                            echo '<tr>
                                                    <td>'.$count.'</td>
                                                    <td><a href="edit-employee.php?edit='.$row["id"].'">'.$row["name"].'</a></td>
                                                    <td>'.$row["_group"].'</td>
                                                    <td>'.$row["position"].'</td>
                                                    <td>'.$row["rate"].'</td>
                                                    <td>'.$row["device"].'</td>
                                                    <td>'.$type_label.'</td>
                                                    <td>'.$row["dt"].'</td>
                                                </tr>';
                                        }
                                    }

                                    if($plate !== "" && !empty($plate_emp_ids) && $remaining > 0 && $parking_total > 0){
                                        $park = parking_db_connect();
                                        if($park){
                                            $parking_offset = max(0, $reports_offset - $main_total);
                                            $parking_sql = "SELECT b.employee_id, r.type, r.dt
                                                            FROM reports r
                                                            LEFT JOIN plates p ON p.plate = r.plate
                                                            LEFT JOIN plate_employee_bindings b ON b.plate_id = p.id
                                                            WHERE b.employee_id IS NOT NULL
                                                            AND b.employee_id IN (".implode(",", array_map("intval", $plate_emp_ids)).")
                                                            $parking_cond
                                                            ORDER BY r.dt DESC
                                                            LIMIT ".(int)$parking_offset.", ".(int)$remaining;
                                            $parking_rows = mysqli_query($park, $parking_sql);
                                            while($parking_rows && ($prow = mysqli_fetch_assoc($parking_rows))){
                                                $emp_id = (int)$prow["employee_id"];
                                                $emp_sql = "SELECT e.id, e.name, g.name AS _group, e.position, e.rate
                                                            FROM employees e
                                                            LEFT JOIN groups g ON e.group_id = g.id
                                                            WHERE e.id = $emp_id";
                                                $emp_rows = mysqli_query($con, $emp_sql);
                                                if(!$emp_rows || mysqli_num_rows($emp_rows) == 0) continue;
                                                $emp = mysqli_fetch_assoc($emp_rows);
                                                $count++;
                                                $p_device = ((int)$prow["type"] == 1) ? "Шлакбаум(вход)" : "Шлакбаум(выход)";
                                                $p_type = ((int)$prow["type"] == 1) ? $lang["check_in"] : $lang["check_out"];
                                                echo '<tr>
                                                        <td>'.$count.'</td>
                                                        <td><a href="edit-employee.php?edit='.$emp["id"].'">'.$emp["name"].'</a></td>
                                                        <td>'.$emp["_group"].'</td>
                                                        <td>'.$emp["position"].'</td>
                                                        <td>'.$emp["rate"].'</td>
                                                        <td>'.$p_device.'</td>
                                                        <td>'.$p_type.'</td>
                                                        <td>'.$prow["dt"].'</td>
                                                    </tr>';
                                            }
                                            mysqli_close($park);
                                        }
                                    }
                                    echo '</tbody>';
                                }
                            ?>
                        </table>
                        <?php echo reports_pagination_html($reports_total, $reports_page, $reports_per_page); ?>
                        </div>
                    </div>
                </div>

                <?php include("widgets/footer.php"); ?>
                
            </div>
        </div>
    </body>
</html>

<script type="text/javascript">
$(document).ready(function () {
    $('#dp1, #dp2').datetimepicker({
        locale:'<?php echo $_SESSION["lang"]; ?>',
        format: 'YYYY-MM-DD HH:mm:ss',
        allowInputToggle: true
    });

    jQuery.fn.dataTable.Api.register('col.index()', function (data){
        var context = this.context[0];
        var idx = null;
        context.aoColumns.forEach(function(col, index){
            if (col.mData == data){
                idx = col.idx;
            }
        });
        return idx;
    });

    var table = $('#tb').DataTable({
        paging: false,
        info: false,
        lengthChange: false,
        dom: 'frt',
        language: <?php echo $_SESSION["lang"]; ?>,
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Excel',
                title: '',
                header: true,
                messageTop: 'BioControl',
                messageBottom: '',
                sheetName: 'BioControl',
                exportOptions: {
                    modifier: {
                        search: 'applied',
                    }
                },
                customize: function( xlsx, button, dt ) {
                    multiSheet = false;
                    var dataSrc = getDataSrc( dt );
                    var groupNames = [... new Set( dt.column( dt.col.index( dataSrc) ).data().toArray() )];
                    if (multiSheet) {
                        var sheetId = 1;
                        groupNames.forEach(function (groupName, index) {
                            if (sheetId === 1) updateSheet1( xlsx, groupName, groupName, button, dt );
                            else addSheet( xlsx, groupName, groupName, groupName, sheetId, button, dt );
                            sheetId++;
                        });     
                    } else {
                        updateSheet1( xlsx, groupNames, '', button, dt );
                    }
                }
            }
        ],
        rowGroup: {
            dataSrc: 2
        },
        order: [[2, "asc"]]
    });
    table.buttons().container().appendTo($('#buttons'));

    var ct = $("#comboTree").comboTree({
        source: <?php echo json_encode($_groups); ?>,
        isMultiple: true,
        cascadeSelect: false,
        selected: [<?php echo $group; ?>]
    });

    ct.onChange(callback);
    function callback(){
        $("[name='group']").val(ct.getSelectedIds().join(','));
    }
});
</script>

<?php mysqli_close($con); ?>