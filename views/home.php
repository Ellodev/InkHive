<?php require "templates/header.php";
require "templates/notification.php";
require_once 'templates/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['loggedin'])) {
        $_SESSION['message'] = [
            'content' => 'You must be logged in to like or comment.',
            'type' => 'warning', // can be 'success', 'danger', 'info', or 'warning'
        ];
    } else {
        if (isset($_POST['like'])) {
            $post_id = $_POST['post_id'];
            $stmt = $db->prepare("SELECT * FROM likes WHERE post_id = :post_id AND user_id = :user_id");
            $stmt->execute([':post_id' => $post_id, ':user_id' => $_SESSION['user_id']]);
            $userLiked = $stmt->fetch();
            if ($userLiked) {
                $stmt = $db->prepare("DELETE FROM likes WHERE post_id = :post_id AND user_id = :user_id");
                $stmt->execute([':post_id' => $post_id , ':user_id' => $_SESSION['user_id']]);
                $_SESSION['message'] = [
                    'content' => 'Removed like.',
                    'type' => 'success', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            } else {
                $stmt = $db->prepare("INSERT INTO likes (post_id, user_id) VALUES (:post_id, :user_id)");
                $stmt->execute([':post_id' => $post_id , ':user_id' => $_SESSION['user_id']]);
                $_SESSION['message'] = [
                    'content' => 'Liked post.',
                    'type' => 'success', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            }
        }
        if (isset($_POST['comment'])) {
            $post_id = $_POST['post_id'];
            $comment = $_POST['comment'];
            try {
                $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, comment_text) VALUES (:post_id, :user_id, :comment)");
                $stmt->execute([':post_id' => $post_id , ':user_id' => $_SESSION['user_id'], ':comment' => htmlspecialchars($comment)]);
                $_SESSION['message'] = [
                    'content' => 'Comment added.',
                    'type' => 'success', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            } catch (PDOException $e) {
                $db->rollBack();
                $_SESSION['message'] = [
                    'content' => 'Error adding comment: ' . $e->getMessage(),
                    'type' => 'danger', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            }
        } else if (isset($_POST['delete-comment'])) {
            $comment_id = $_POST['comment_id'];
            $stmt = $db->prepare("SELECT * FROM comments WHERE comment_id = :comment_id AND user_id = :user_id");
            $stmt->execute([':comment_id' => $comment_id, ':user_id' => $_SESSION['user_id']]);
            $ownComment = $stmt->fetch();
            if ($ownComment) {
                $stmt = $db->prepare("DELETE FROM comments WHERE comment_id = :comment_id");
                $stmt->execute([':comment_id' => $comment_id]);
                $_SESSION['message'] = [
                    'content' => 'Comment deleted.',
                    'type' => 'success', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            } else {
                $_SESSION['message'] = [
                    'content' => 'You can only delete your own comments.',
                    'type' => 'warning', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            }
        } else if (isset($_POST['delete-post'])) {
            $post_id = $_POST['post_id'];
            $stmt = $db->prepare("SELECT * FROM posts WHERE post_id = :post_id AND user_id = :user_id");
            $stmt->execute([':post_id' => $post_id, ':user_id' => $_SESSION['user_id']]);
            $ownPost = $stmt->fetch();
            if ($ownPost) {
                $stmt = $db->prepare("DELETE FROM posts WHERE post_id = :post_id");
                $stmt->execute([':post_id' => $post_id]);
                $_SESSION['message'] = [
                    'content' => 'Post deleted.',
                    'type' => 'success', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            } else {
                $_SESSION['message'] = [
                    'content' => 'You can only delete your own posts.',
                    'type' => 'warning', // can be 'success', 'danger', 'info', or 'warning'
                ];
                header('Location: home');
            }
        }
    }

}
?>

<h1 class="is-size-1 has-text-centered title">posts</h1>

<div class="is-flex is-justify-content-center is-flex-direction-column is-flex-wrap is-align-items-center">
    <?php
    $query = "
    SELECT posts.*, users.username, users.profile_picture
    FROM posts
    JOIN users ON posts.user_id = users.user_id
    ORDER BY posts.created_at DESC
    ";

    $posts = $db->query($query)->fetchAll();

    foreach ($posts as $post) {
        $post_id = $post['post_id'];

        $stmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE post_id = :post_id");
        $stmt->execute([':post_id' => $post_id]);
        $likesCount = $stmt->fetchColumn();
        $userLiked = false;

        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT * FROM likes WHERE post_id = :post_id AND user_id = :user_id");
            $stmt->execute([':post_id' => $post_id, ':user_id' => $_SESSION['user_id']]);
            $userLiked = $stmt->fetch();
        }

        $ownUserPost = false;
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT * FROM posts WHERE post_id = :post_id AND user_id = :user_id");
            $stmt->execute([':post_id' => $post_id, ':user_id' => $_SESSION['user_id']]);
            $ownUserPost = $stmt->fetch();
        }

        $commentsQuery = "
        SELECT comments.*, users.username, users.profile_picture
        FROM comments
        JOIN users ON comments.user_id = users.user_id
        WHERE comments.post_id = :post_id
        ORDER BY comments.created_at DESC
        ";
        $stmt = $db->prepare($commentsQuery);
        $stmt->execute([':post_id' => $post_id]);
        $comments = $stmt->fetchAll();
        ?>
        <div class="card" id="post" style="width: 600px; margin: 0 auto 20px;">
            <div class="card-image">
                <figure class="image is-16by9">
                    <img
                            src="<?= htmlspecialchars($post['image']) ?>"
                            alt="Post Image"
                            class="post-image"
                            onerror="this.style.display='none'; this.closest('.card-image').style.display='none';"
                    >
                </figure>
            </div>

            <div class="card-content">
                <h3 class="title is-3"><?= $post['title'] ?></h3>
                <div class="is-flex is-flex-direction-row is-align-items-center">
                    <figure class="image is-32x32" style="overflow: hidden">
                        <img src="<?= isset($post['profile_picture']) && !empty($post['profile_picture'])
                            ? htmlspecialchars($post['profile_picture'])
                            : 'uploads/default-avatar-light.png'; ?>"
                             alt="Profile Picture"
                             style="object-fit: cover; border-radius: 50%; width: 100%; height: 100%">
                    </figure>
                    <p class="subtitle is-6 has-text-grey-light">
                         <strong><?= $post['username']  ?></strong> at <?= $post['created_at'] ?>
                    </p>
                </div>
                <?php if ($ownUserPost) { ?>
                    <form method="POST" action="" class="is-flex is-justify-content-flex-end">
                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                        <button type="submit" name="delete-post" class="button is-danger is-small">
                            Delete
                        </button>
                    </form>
                <?php } ?>

                <div class="content">
                    <?= nl2br($post['content']) ?>
                </div>

                <div class="columns is-mobile is-vcentered mt-3">
                    <form method="POST" action="" class="column is-narrow">
                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                        <button type="submit" name="like"
                            <?= (!isset($_SESSION['loggedin']) ||$ownUserPost) ? 'disabled' : '' ?>
                                class="button <?= $userLiked ? 'is-danger' : 'is-light' ?>">
                            <span class="icon">
                                <i class="fa<?= $userLiked ? '-solid' : '-regular' ?> fa-heart"></i>
                            </span>
                            <span><?= $likesCount ?></span>
                        </button>
                    </form>

                    <form method="POST" action="" class="column">
                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                        <div class="field has-addons">
                            <div class="control is-expanded">
                                <input type="text" name="comment" id="comment"
                                       class="input"
                                    <?= (!isset($_SESSION['loggedin'])) ? 'disabled placeholder="You must be logged in to comment."' : 'placeholder="Add a comment..."' ?>>
                            </div>
                            <div class="control">
                                <button type="submit" name="comment-button" class="button is-info">
                                    Comment
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-content">
                <?php foreach ($comments as $comment): ?>
                    <div class="notification is-flex is-align-items-center">
                        <figure class="image is-24x24" style="overflow: hidden">
                            <img src="<?= isset($comment['profile_picture']) && !empty($comment['profile_picture'])
                                ? htmlspecialchars($comment['profile_picture'])
                                : 'uploads/default-avatar-light.png'; ?>"
                                 alt="Profile Picture"
                                 style="object-fit: cover; border-radius: 50%; width: 100%; height: 100%">
                        </figure>
                        <p><strong><?= $comment['username'] ?>:</strong> <?= nl2br($comment['comment_text']) ?></p>
                        <?php
                        if (isset($_SESSION['user_id']) && $comment['user_id'] == $_SESSION['user_id']) {
                            ?>
                            <form method="POST" action="">
                                <input type="hidden" name="comment_id" value="<?= $comment['comment_id']; ?>">
                                <button type="submit" name="delete-comment" class="button is-danger is-small">
                                    Delete
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php } ?>
</div>

<script>
    window.addEventListener('beforeunload', function () {
        localStorage.setItem('scrollPosition', window.scrollY);
    });

    window.addEventListener('load', function () {
        const savedPosition = localStorage.getItem('scrollPosition');
        if (savedPosition !== null) {
            window.scrollTo(0, savedPosition);
            localStorage.removeItem('scrollPosition');
        }
    });
</script>