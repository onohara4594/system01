<?php
require_once(dirname(__FILE__) . '/../config/config.php');
require_once(dirname(__FILE__) . '/../functions.php');

try {
  session_start();

  if (isset($_SESSION['USER']) && $_SESSION['USER']['auth_type'] == 1) {
    //ログイン済みの場合はHOME画面へ
    redirect('./user.php');
    exit;
  }
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //POST処理時

    check_token();

    //1.入力値を取得
    $user_id = $_POST['user_id'];
    $password = $_POST['password'];

    //2.バリデーションチェック
    $err = array();
    if (!$user_id) {
      $err['user_id'] = '社員IDを入力して下さい。';
    } elseif (!preg_match('/^[0-9]+$/', $user_id)) {
      $err['user_id'] = '社員IDを正しく入力して下さい。';
    } elseif (mb_strlen($user_id, 'utf-8') > 20) {
      $err['user_id'] = '社員IDが長すぎます。';
    }
    if (!$password) {
      $err['password'] = 'パスワードを入力して下さい。';
    }

    if (empty($err)) {
      //3.データベースに照合
      $pdo = connect_db();
      $sql = "SELECT * FROM user WHERE user_id = :user_id AND auth_type = 1 LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['password'])) {
        //4.ログイン処理（セッションに保存）
        $_SESSION['USER'] = $user;

        //5.HOME画面へ遷移
        redirect('./user.php');
        exit;
      } else {
        $err['password'] = '認証に失敗しました。';
      }
    }
  } else {
    //画面初回アクセス時
    $user_id = "";
    $password = "";

    set_token();

  }
  $page_title = '日報登録システム';
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
  <h1>Web日報登録</h1>

  <form class="border rounded form-login" method="post">
    <h2 class="h3 my-3">Login</h2>

    <div class="form-group pt-3">
      <input type="text" class="form-control rounded-pill<?php if (isset($err['user_id'])) echo ' is-invalid'; ?>" 
      name="user_id" value="<?= $user_id ?>" placeholder="社員ID" required>
      <div class="invalid-feedback"><?= $err['user_id'] ?></div>
    </div>

    <div class="form-group pt-3">
      <input type="password" class="form-control rounded-pill<?php if (isset($err['password'])) echo ' is-invalid'; ?>" 
      name="password" placeholder="password">
      <div class="invalid-feedback"><?= $err['password'] ?></div>
    </div>
    <button type="submit" class="btn btn-primary rounded-pill mt-3">ログイン</button>
    <input type="hidden" name="CSRF_TOKEN" value="<?= $_SESSION['CSRF_TOKEN'] ?>">
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