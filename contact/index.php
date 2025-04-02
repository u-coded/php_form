<?php
session_start();

// エスケープ関数
if (!function_exists("h")) {
  function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
  }
}

// CSRFトークン生成（最初の表示時のみ）
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mode = "input";

if (isset($_POST["back"])) {
  // 修正画面 → 特に処理なし

} elseif (isset($_POST["confirm"])) {

  // CSRFトークン検証
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("不正なリクエストです");
  }

  $mode = "confirm";

  $fields = ['type', 'title', 'message', 'sei', 'mei', 'sei_kana', 'mei_kana', 'company', 'email', 'email_conf', 'agree'];
  foreach ($fields as $field) {
    $_SESSION[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
  }

  if (isset($_POST['tel'])) {
    $tel = trim($_POST['tel']);
    $tel = mb_ereg_replace("[ー－]", "-", $tel);
    $_SESSION["tel"] = mb_convert_kana($tel, "rna");
  } else {
    $_SESSION["tel"] = "";
  }

  // バリデーション
  $error_msg = [];

  if (!$_SESSION["type"]) {
    $error_msg[] = "お問い合わせ項目を選択してください。";
  }
  if (!$_SESSION["title"]) {
    $error_msg[] = "件名を入力してください。";
  } elseif (mb_strlen($_SESSION["title"]) > 100) {
    $error_msg[] = "件名は100文字以内で入力してください。";
  }

  if (!$_SESSION["message"]) {
    $error_msg[] = "お問い合わせ内容を入力してください。";
  } elseif (mb_strlen($_SESSION["message"]) > 2000) {
    $error_msg[] = "お問い合わせ内容は2000文字以内で入力してください。";
  }

  if (!$_SESSION["sei"]) {
    $error_msg[] = "姓を入力してください。";
  } elseif (mb_strlen($_SESSION["sei"]) > 30) {
    $error_msg[] = "姓は30文字以内で入力してください。";
  }

  if (!$_SESSION["mei"]) {
    $error_msg[] = "名を入力してください。";
  } elseif (mb_strlen($_SESSION["mei"]) > 30) {
    $error_msg[] = "名は30文字以内で入力してください。";
  }

  if (!$_SESSION["sei_kana"]) {
    $error_msg[] = "せいを入力してください。";
  } elseif (mb_strlen($_SESSION["sei_kana"]) > 30) {
    $error_msg[] = "せいは30文字以内で入力してください。";
  }

  if (!$_SESSION["mei_kana"]) {
    $error_msg[] = "めいを入力してください。";
  } elseif (mb_strlen($_SESSION["mei_kana"]) > 30) {
    $error_msg[] = "めいは30文字以内で入力してください。";
  }

  if (mb_strlen($_SESSION["company"]) > 100) {
    $error_msg[] = "会社名は100文字以内で入力してください。";
  }

  if (!$_SESSION["email"]) {
    $error_msg[] = "メールアドレスを入力してください。";
  } elseif (!filter_var($_SESSION["email"], FILTER_VALIDATE_EMAIL)) {
    $error_msg[] = "正しいメールアドレスを入力してください。";
  }

  if (!$_SESSION["email_conf"]) {
    $error_msg[] = "メールアドレス確認用を入力してください。";
  } elseif ($_SESSION["email"] !== $_SESSION["email_conf"]) {
    $error_msg[] = "メールアドレスが一致しません。";
  }

  if ($_SESSION["tel"] && !preg_match('/^0\d{9,10}$/', str_replace('-', '', $_SESSION["tel"]))) {
    $error_msg[] = "電話番号の形式が正しくありません。";
  }

  if (!$_SESSION["agree"]) {
    $error_msg[] = "プライバシーポリシーに同意していただけない場合、送信できません。";
  }

  if ($error_msg) {
    $mode = "input";
  }
} elseif (isset($_POST["send"])) {

  // CSRFトークン検証
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("不正なリクエストです");
  }

  $mode = "send";
  $mail_success = false;

  if (isset($_SESSION["type"])) {
    date_default_timezone_set("Asia/Tokyo");
    mb_language("ja");
    mb_internal_encoding("UTF-8");

    $from_mail = "test-email@test.jp";
    $from_name = mb_encode_mimeheader("〇〇会社名");
    $header = "Content-Type: text/plain; charset=UTF-8\r\n";
    $header .= "From: {$from_name} <{$from_mail}>\r\n";
    $header .= "Reply-To: {$from_mail}\r\n";

    $auto_reply_subject = "お問い合わせありがとうございます。";
    $auto_reply_text = "この度はお問い合わせいただき、ありがとうございます。\n\n";
    $auto_reply_text .= "お問い合わせ内容：\n";
    $auto_reply_text .= "お問い合わせ項目：" . $_SESSION["type"] . "\n";
    $auto_reply_text .= "件名：" . $_SESSION["title"] . "\n";
    $auto_reply_text .= "内容：" . $_SESSION["message"] . "\n\n";
    $auto_reply_text .= "お名前：" . $_SESSION["sei"] . $_SESSION["mei"] . " 様\n";
    $auto_reply_text .= "ふりがな：" . $_SESSION["sei_kana"] . $_SESSION["mei_kana"] . "\n";
    $auto_reply_text .= "会社名：" . $_SESSION["company"] . "\n";
    $auto_reply_text .= "メール：" . $_SESSION["email"] . "\n";
    $auto_reply_text .= "電話番号：" . $_SESSION["tel"] . "\n";
    $auto_reply_text .= "送信日時：" . date("Y-m-d H:i") . "\n";

    $admin_reply_subject = "お問い合わせを受け付けました";
    $admin_reply_text = $auto_reply_text;

    $user_sent = mb_send_mail($_SESSION["email"], $auto_reply_subject, $auto_reply_text, $header);
    $admin_sent = mb_send_mail($from_mail, $admin_reply_subject, $admin_reply_text, $header);

    $mail_success = $user_sent && $admin_sent;

    $_SESSION = []; // リセット
  } else {
    $mode = "input";
  }
} else {
  // フォーム初期表示時に必要な項目だけクリア（csrf_tokenは保持）
  foreach ($_SESSION as $key => $val) {
    if ($key !== 'csrf_token') {
      unset($_SESSION[$key]);
    }
  }
}
?>

<!-- ▼ここからHTML -->
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <title>お問い合わせ | 〇〇会社名</title>
</head>

<body>
  <main>
    <h1>お問い合わせフォーム</h1>

    <?php if ($mode === "confirm"): ?>
      <form action="" method="post">
        <p>以下の内容で送信します。よろしければ「送信する」をクリックしてください。</p>
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
        <table>
          <tr>
            <th>項目</th>
            <td><?php echo h($_SESSION["type"]); ?></td>
          </tr>
          <tr>
            <th>件名</th>
            <td><?php echo h($_SESSION["title"]); ?></td>
          </tr>
          <tr>
            <th>内容</th>
            <td><?php echo nl2br(h($_SESSION["message"])); ?></td>
          </tr>
          <tr>
            <th>氏名</th>
            <td><?php echo h($_SESSION["sei"] . $_SESSION["mei"]); ?></td>
          </tr>
          <tr>
            <th>ふりがな</th>
            <td><?php echo h($_SESSION["sei_kana"] . $_SESSION["mei_kana"]); ?></td>
          </tr>
          <tr>
            <th>会社名</th>
            <td><?php echo h($_SESSION["company"]); ?></td>
          </tr>
          <tr>
            <th>メール</th>
            <td><?php echo h($_SESSION["email"]); ?></td>
          </tr>
          <tr>
            <th>電話番号</th>
            <td><?php echo h($_SESSION["tel"]); ?></td>
          </tr>
        </table>
        <button type="submit" name="back">修正する</button>
        <button type="submit" name="send">送信する</button>
      </form>

    <?php elseif ($mode === "send"): ?>
      <?php if ($mail_success): ?>
        <p>送信ありがとうございました。</p>
      <?php else: ?>
        <p>送信に失敗しました。</p>
      <?php endif; ?>
      <p><a href="">トップページへ戻る</a></p>

    <?php else: ?>
      <?php if (!empty($error_msg)): ?>
        <p><?php echo implode('<br>', array_map('h', $error_msg)); ?></p>
      <?php endif; ?>

      <form action="" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
        <label>お問い合わせ項目：<input type="text" name="type" value="<?php echo h($_SESSION["type"] ?? ""); ?>"></label><br>
        <label>件名：<input type="text" name="title" value="<?php echo h($_SESSION["title"] ?? ""); ?>"></label><br>
        <label>内容：<textarea name="message"><?php echo h($_SESSION["message"] ?? ""); ?></textarea></label><br>
        <label>姓：<input type="text" name="sei" value="<?php echo h($_SESSION["sei"] ?? ""); ?>"></label>
        <label>名：<input type="text" name="mei" value="<?php echo h($_SESSION["mei"] ?? ""); ?>"></label><br>
        <label>せい：<input type="text" name="sei_kana" value="<?php echo h($_SESSION["sei_kana"] ?? ""); ?>"></label>
        <label>めい：<input type="text" name="mei_kana" value="<?php echo h($_SESSION["mei_kana"] ?? ""); ?>"></label><br>
        <label>会社名：<input type="text" name="company" value="<?php echo h($_SESSION["company"] ?? ""); ?>"></label><br>
        <label>メール：<input type="email" name="email" value="<?php echo h($_SESSION["email"] ?? ""); ?>"></label><br>
        <label>メール確認：<input type="email" name="email_conf" value="<?php echo h($_SESSION["email_conf"] ?? ""); ?>"></label><br>
        <label>電話番号：<input type="text" name="tel" value="<?php echo h($_SESSION["tel"] ?? ""); ?>"></label><br>
        <label><input type="checkbox" name="agree" value="同意する" <?php echo (!empty($_SESSION["agree"])) ? 'checked' : ''; ?>> プライバシーポリシーに同意する</label><br>
        <button type="submit" name="confirm">確認する</button>
      </form>
    <?php endif; ?>

  </main>
</body>

</html>
