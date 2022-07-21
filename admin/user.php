<?php
require_once(dirname(__FILE__) . '/../config/config.php');
require_once(dirname(__FILE__) . '/../functions.php');

try {
    session_start();
    if (!isset($_SESSION['USER']) || $_SESSION['USER']['auth_type'] != 1) {
        //ログインされていない場合はログイン画面へ
        redirect('./login.php');
        exit;
    }
    $pdo = connect_db();
    $sql = "SELECT * FROM user";
    $stmt = $pdo->query($sql);
    $list = $stmt->fetchAll();

    $page_title = '社員リスト';
} catch (Exception $e) {
    //エラー時の処理
    redirect('../error.php');
    exit;
}
?>
<!doctype html>
<html lang="ja">

<?php include(dirname(__FILE__) . '/../templates/head_tag_admin.php'); ?>

<body class="text-center bg-Success">

    <h1 class="my-3">社員一覧</h1>
    <form class="border rounded form-user-list" action="list.php">
        <table class="table table-bordered">
            <thead>
                <tr class="bg-light">
                    <th scope="col">社員ID</th>
                    <th scope="col">社員名</th>
                    <th scope="col">生年月日</th>
                    <th scope="col">権限</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                <?php foreach ($list as $user) : ?>
                    <tr>
                        <th scope="row"><?= $user['user_id'] ?></th>
                        <td><a href="./list.php?id=<?= $user['id'] ?>"><?= $user['user_name'] ?></td>
                        <td scope="row"><?= $user['birthday'] ?></td>
                        <td scope="row"><?php if ($user['auth_type'] == 1) echo '管理者' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->
    <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
    -->
</body>
</html>