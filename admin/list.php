<?php
require_once(dirname(__FILE__) . '/..config/config.php');
require_once(dirname(__FILE__) . '/../functions.php');

try {
  session_start();

  if (!isset($_SESSION['USER']) || $_SESSION['USER']['auth_type'] != 1) {
    //ログインされていない場合はログイン画面へ
    header('Location:/admin/login.php');
    exit;
  }

  //対象ユーザーのIDをパラメーターから取得
  $user_id = $_REQUEST['id'];
  if (!$user_id) {
    throw new Exception('ユーザーIDが不正', 500);
  }

  $pdo = connect_db();
  $err = array();

  //処理対処の日付
  $target_date = date('Y-m-d');

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //日報登録処理
    check_token();

    //入力値をPOSTパラメータから取得
    $target_date = $_POST['target_date'];
    $modal_start_time = $_POST['modal_start_time'];
    $modal_finish_time = $_POST['modal_finish_time'];
    $modal_rest_time = $_POST['modal_rest_time'];
    $modal_comment = $_POST['modal_comment'];

    //出勤時間の必須/形式チェック
    if (!$modal_start_time) {
      $err['modal_start_time'] = '出勤時間を入力して下さい。';
    } elseif (!check_time_format($modal_start_time)) {
      $modal_start_time = '';
      $err['modal_start_time'] = '出勤時間を正しく入力して下さい。';
    }
    //退勤時間の必須/形式チェック
    if (!check_time_format($modal_finish_time)) {
      $modal_finish_time = '';
      $err['modal_finish_time'] = '退勤時間を正しく入力して下さい。';
    }
    //休憩時間の必須/形式チェック
    if (!check_time_format($modal_rest_time)) {
      $modal_rest_time = '';
      $err['modal_rest_time'] = '休憩時間を正しく入力して下さい。';
    }
    //業務内容の最大サイズチェック
    if (mb_strlen($modal_comment, 'utf-8') > 2000) {
      $err['modal_comment'] = '業務内容が長すぎます。';
    }

    if (empty($err)) {
      //対象日のデータがあるかチェクする
      $sql = "SELECT id FROM work WHERE user_id = :user_id AND date = :date LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':user_id', (int)$user_id, PDO::PARAM_INT);
      $stmt->bindValue(':date', $target_date, PDO::PARAM_STR);
      $stmt->execute();
      $work = $stmt->fetch();

      if ($work) {
        //対象日のデータがあればUPDATE
        $sql = "UPDATE work SET start_time = :start_time, finish_time = :finish_time, rest_time = :rest_time, comment = :comment WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$work['id'], PDO::PARAM_INT);
        $stmt->bindValue(':start_time', $modal_start_time, PDO::PARAM_STR);
        $stmt->bindValue(':finish_time', $modal_finish_time, PDO::PARAM_STR);
        $stmt->bindValue(':rest_time', $modal_rest_time, PDO::PARAM_STR);
        $stmt->bindValue(':comment', $modal_comment, PDO::PARAM_STR);
        $stmt->execute();
      } else {
        //対象日のデータが無ければINSERT
        $sql = "INSERT INTO work (id,user_id, date, start_time, finish_time, rest_time, comment) 
        VALUES (:id, :user_id, :date, :start_time, :finish_time, :rest_time, :comment)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$work['id'], PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int)$user_id, PDO::PARAM_INT);
        $stmt->bindValue(':date', $target_date, PDO::PARAM_STR);
        $stmt->bindValue(':start_time', $modal_start_time, PDO::PARAM_STR);
        $stmt->bindValue(':finish_time', $modal_finish_time, PDO::PARAM_STR);
        $stmt->bindValue(':rest_time', $modal_rest_time, PDO::PARAM_STR);
        $stmt->bindValue(':comment', $modal_comment, PDO::PARAM_STR);
        $stmt->execute();
      }
    }
  } else {
    $modal_start_time = '';
    $modal_finish_time = '';
    $modal_rest_time = '01:00';
    $modal_comment = '';

    set_token();
  }

  //2.ユーザーの業務日報データを取得
  if (isset($_GET['m'])) {
    $yyyymm = $_GET['m'];
    $day_count = date('t', strtotime($yyyymm));

    if (count(explode('-', $yyyymm)) != 2) {
      throw new Exception('日付の指定が不正', 500);
    }

    //今月〜過去12ヶ月の範囲内かどうか
    $check_date = new DateTime($yyyymm . '-01');
    $start_date = new DateTime('first day of -11 month 00:00');
    $end_date = new DateTime('first day of this month 00:00');

    if ($check_date < $start_date || $end_date < $check_date) {
      throw new Exception('日付の範囲が不正', 500);
    }
  } else {
    $yyyymm = date('Y-m');
    $day_count = date('t');
  }
  
  //指定年月日の勤怠情報を取得
  $sql = "SELECT date, id, user_id, start_time, finish_time, rest_time, comment FROM work 
  WHERE user_id = :user_id AND DATE_FORMAT(date,'%Y-%m') = :date";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':user_id', (int)$user_id, PDO::PARAM_INT);
  $stmt->bindValue(':date', $yyyymm, PDO::PARAM_STR);
  $stmt->execute();
  $work = $stmt->fetchAll(PDO::FETCH_UNIQUE);

  $page_title = '業務リスト';
} catch (Exception $e) {
  //エラー時の処理
  header('Location:/error.php');
  exit;
}
?>
<!doctype html>
<html lang="ja">

<?php include(dirname(__FILE__) . '/../templates/head_tag_admin.php'); ?>

<body class="text-center bg-Success">

  <h1 class="my-3">月別リスト</h1>

  <form class="border rounded form-time-table" action="list.php">

    <div class="float-start">
      <select class="form-select rounded-pill my-3 w-auto" name="m" onchange="submit(this.form)">
        <option value="<?= date('Y-m') ?>"><?= date('Y/m') ?></option>
        <?php for ($i = 1; $i < 12; $i++) : ?>
          <?php $target_yyyymm = strtotime("-{$i}month"); ?>
          <option value="<?= date('Y-m', $target_yyyymm) ?>" <?php if ($yyyymm == date('Y-m', $target_yyyymm)) echo 'selected' ?>>
            <?= date('Y/m', $target_yyyymm) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="float-end">
      <a href="/admin/user.php"><button type="button" class="btn btn-secondary w-auto my-3 rounded-pill">社員一覧に戻る</button></a>
    </div>

    <table class="table table-bordered">
      <thead>
        <tr class="bg-light">
          <th class="fix-col">日</th>
          <th class="fix-col">出勤</th>
          <th class="fix-coll">退社</th>
          <th class="fix-col">休憩</th>
          <th>業務内容</th>
          <th class="fix-col"> </th>
        </tr>
      </thead>
      <tbody class="bg-white">

        <?php for ($i = 1; $i <= $day_count; $i++) : ?>
          <?php
          $start_time = '';
          $finish_time = '';
          $rest_time = '';
          $comment = '';
          if (isset($work[date('Y-m-d', strtotime($yyyymm . '-' . $i))])) {
            $day = $work[date('Y-m-d', strtotime($yyyymm . '-' . $i))];
            if ($day['start_time']) {
              $start_time = date('H:i', strtotime($day['start_time']));
            }
            if ($day['finish_time']) {
              $finish_time = date('H:i', strtotime($day['finish_time']));
            }
            if ($day['rest_time']) {
              $rest_time = date('H:i', strtotime($day['rest_time']));
            }
            if ($day['comment']) {
              $comment = mb_strimwidth($day['comment'], 0, 40, '...');
            }
          }
          ?>
          <tr>
            <th scope="row"><?= time_format_dw($yyyymm . '-' . $i) ?></th>
            <td><?= $start_time ?></td>
            <td><?= $finish_time ?></td>
            <td><?= $rest_time ?></td>
            <td><?= h($comment) ?></td>
            <td class="d-none"><?= h($comment_long) ?></td>
            <td><button type="button" class="btn btn-default h-auto py-0" data-bs-toggle="modal" data-bs-target="#inputModal" 
            data-day="<?= $yyyymm . '-' . sprintf('%02d', $i) ?>" data-month="<?= date('n', strtotime($yyyymm . '-' . $i)) ?>">
            <i class="fa-solid fa-pen-to-square"></i></button></td>
          </tr>

        <?php endfor; ?>
      </tbody>
    </table>
    <input type="hidden" name="id" value="<?= $user_id ?>">
  </form>

  <!-- Modal -->
  <form method="POST">
    <div class="modal fade" id="inputModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <p></p>
            <h5 class="modal-title" id="exampleModalLabel">日報登録</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
            </button>
          </div>
          <div class="modal-body">

            <div class="container">
              <div class="alert alert-primary" role="alert">
                <span id="modal_month"><?= date('n', strtotime($target_date)) ?></span>/<span id="modal_day"><?= time_format_dw($target_date) ?></span>
              </div>
              <div class="row">
                <div class="col">
                  <div class="input-group">
                    <input type="text" class="form-control <?php if (isset($err['modal_start_time'])) echo ' is-invalid'; ?>" placeholder="出勤" id="modal_start_time" name="modal_start_time" value="<?= format_time($modal_start_time) ?>" required>
                    <div class="input-group-prepend">
                      <button type="button" class="btn btn-outline-primary" id="start_btn">打刻</button>
                    </div>
                    <div class="invalid-feedback"><?= $err['modal_start_time'] ?></div>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <input type="text" class="form-control <?php if (isset($err['modal_finish_time'])) echo ' is-invalid'; ?>" placeholder="退勤" id="modal_finish_time" name="modal_finish_time" value="<?= format_time($modal_finish_time) ?>">
                    <div class="input-group-prepend">
                      <button type="button" class="btn btn-outline-primary" id="finish_btn">打刻</button>
                    </div>
                    <div class="invalid-feedback"><?= $err['modal_finish_time'] ?></div>
                  </div>
                </div>
                <div class="col">
                  <input type="text" class="form-control <?php if (isset($err['modal_rest_time'])) echo ' is-invalid'; ?>" placeholder="休憩" id="modal_rest_time" name="modal_rest_time" value="<?= format_time($modal_rest_time) ?>">
                  <div class="invalid-feedback"><?= $err['modal_rest_time'] ?></div>
                </div>
              </div>
              <div class="form-floating pt-3">
                <textarea class="form-control <?php if (isset($err['modal_comment'])) echo ' is-invalid'; ?>" id="modal_comment" name="modal_comment" placeholder="業務内容"><?= $modal_comment ?></textarea>
                <div class="invalid-feedback"><?= $err['modal_comment'] ?></div>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary text-white rounded-pill">登録</button>
          </div>
        </div>
      </div>
    </div>
    <input type="hidden" id="target_date" name="target_date" value="<?= ($target_date) ?>">
    <input type="hidden" name="CSRF_TOKEN" value="<?= $_SESSION['CSRF_TOKEN'] ?>">
  </form>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  <script>
    <?php if (!empty($err)) : ?>
      var inputModal = new bootstrap.Modal(document.getElementById('inputModal'));
      inputModal.toggle();
    <?php endif; ?>

    $('#start_btn').click(function() {
      const now = new Date();
      const hour = now.getHours().toString().padStart(2, '0');
      const minute = now.getMinutes().toString().padStart(2, '0');
      $('#modal_start_time').val(hour + ':' + minute);
    })

    $('#finish_btn').click(function() {
      const now = new Date();
      const hour = now.getHours().toString().padStart(2, '0');
      const minute = now.getMinutes().toString().padStart(2, '0');
      $('#modal_finish_time').val(hour + ':' + minute);
    })

    $('#inputModal').on('show.bs.modal', function(event) {
      var button = $(event.relatedTarget)
      var target_month = button.data('month')
      var target_day = button.data('day')

      /*編集ボタンが押された対象日の表データを取得*/
      var day = button.closest('tr').children('th')[0].innerText
      var start_time = button.closest('tr').children('td')[0].innerText
      var finish_time = button.closest('tr').children('td')[1].innerText
      var rest_time = button.closest('tr').children('td')[2].innerText
      var comment = button.closest('tr').children('td')[3].innerText

      /*取得したデータをモーダルの名簿に設定*/
      $('#modal_month').text(target_month)
      $('#modal_day').text(day)
      $('#modal_start_time').val(start_time)
      $('#modal_finish_time').val(finish_time)
      $('#modal_rest_time').val(rest_time)
      $('#modal_comment').val(comment)
      $('#target_date').val(target_day)

      /*エラー表示クリア*/
      $('#modal_start_time').removeClass('is-invalid')
      $('#modal_finish_time').removeClass('is-invalid')
      $('#modal_rest_time').removeClass('is-invalid')
      $('#modal_comment').removeClass('is-invalid')

    })
  </script>
  <!-- Option 2: Separate Popper and Bootstrap JS -->
  <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
    -->
</body>
</html>