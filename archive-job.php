<?php
/**
 * Template Name: 求人アーカイブ
 */
get_header();

// クエリ変数から検索条件を取得
$location_slug = get_query_var('job_location');
$position_slug = get_query_var('job_position');
$job_type_slug = get_query_var('job_type');
$facility_type_slug = get_query_var('facility_type');
$job_feature_slug = get_query_var('job_feature');
$features_only = get_query_var('job_features_only');
$search_query = get_search_query(); // キーワード検索クエリを取得

// URLクエリパラメータから特徴の配列を取得（複数選択の場合）
$feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();

// 特徴のスラッグが単一で指定されている場合、それも追加
if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
    $feature_slugs[] = $job_feature_slug;
}

// カスタムクエリを構築
$tax_query = array();

if (!empty($location_slug)) {
    $tax_query[] = array(
        'taxonomy' => 'job_location',
        'field'    => 'slug',
        'terms'    => $location_slug,
    );
}

if (!empty($position_slug)) {
    $tax_query[] = array(
        'taxonomy' => 'job_position',
        'field'    => 'slug',
        'terms'    => $position_slug,
    );
}

if (!empty($job_type_slug)) {
    $tax_query[] = array(
        'taxonomy' => 'job_type',
        'field'    => 'slug',
        'terms'    => $job_type_slug,
    );
}

if (!empty($facility_type_slug)) {
    $tax_query[] = array(
        'taxonomy' => 'facility_type',
        'field'    => 'slug',
        'terms'    => $facility_type_slug,
    );
}

// 特徴の配列が空でない場合、tax_queryに追加
if (!empty($feature_slugs)) {
    $tax_query[] = array(
        'taxonomy' => 'job_feature',
        'field'    => 'slug',
        'terms'    => $feature_slugs,
        'operator' => 'IN',
    );
}

// tax_queryが複数ある場合はAND条件に設定
if (count($tax_query) > 1) {
    $tax_query['relation'] = 'AND';
}

// 検索条件を表示用にまとめる
$conditions = array();

if (!empty($location_slug)) {
    $location_term = get_term_by('slug', $location_slug, 'job_location');
    if ($location_term) {
        $conditions[] = $location_term->name;
    }
}

if (!empty($position_slug)) {
    $position_term = get_term_by('slug', $position_slug, 'job_position');
    if ($position_term) {
        $conditions[] = $position_term->name;
    }
}

if (!empty($job_type_slug)) {
    $job_type_term = get_term_by('slug', $job_type_slug, 'job_type');
    if ($job_type_term) {
        $conditions[] = $job_type_term->name;
    }
}

if (!empty($facility_type_slug)) {
    $facility_type_term = get_term_by('slug', $facility_type_slug, 'facility_type');
    if ($facility_type_term) {
        $conditions[] = $facility_type_term->name;
    }
}

// 特徴の表示名を取得
$feature_names = array();
foreach ($feature_slugs as $slug) {
    $feature_term = get_term_by('slug', $slug, 'job_feature');
    if ($feature_term) {
        $feature_names[] = $feature_term->name;
        if (!in_array($feature_term->name, $conditions)) {
            $conditions[] = $feature_term->name;
        }
    }
}

// キーワード検索を条件に追加
if (!empty($search_query)) {
    $conditions[] = '"' . $search_query . '"';
}

// メインクエリを変更
global $wp_query;

// 現在のページネーション情報を保持
$paged = get_query_var('paged') ? get_query_var('paged') : 1;

$args = array(
    'post_type' => 'job',
    'posts_per_page' => 10,
    'paged' => $paged,
);

// タクソノミークエリの追加
if (!empty($tax_query)) {
    $args['tax_query'] = $tax_query;
}

// 検索キーワードがある場合
if (!empty($search_query)) {
    $args['s'] = $search_query;
}

// クエリを上書き - 条件にかかわらず常に実行
$wp_query = new WP_Query($args);
?>

<!-- ここ以下は元のコードと同じ -->



<script>
// DOM読み込み後にサイドバーを強制的に非表示
document.addEventListener('DOMContentLoaded', function() {
    // サイドバーを非表示
    var sidebarElements = document.querySelectorAll('#sidebar, .sidebar, #secondary, .widget-area');
    sidebarElements.forEach(function(element) {
        element.style.display = 'none';
    });
    
    // メインコンテンツを100%幅に
    var mainElements = document.querySelectorAll('#main, .main, #primary, .content-area, .container');
    mainElements.forEach(function(element) {
        element.style.width = '100%';
        element.style.maxWidth = '100%';
        element.style.float = 'none';
    });
});
</script>
<div class="breadcrumb-container">
<?php display_breadcrumb(); ?>
</div>
<div class="job-listing-wrapper">
    <div class="job-listing-container">
        <div class="job-search-header">
    <h1 class="page-title">
        <?php if (!empty($conditions)): ?>
            <?php echo implode(' × ', $conditions); ?>の求人情報
        <?php else: ?>
            求人情報一覧
        <?php endif; ?>
    </h1>
    <div class="job-count">
        <p>検索結果: <span class="count-number"><?php echo esc_html($wp_query->found_posts); ?></span>件</p>
    </div>
    
    <!-- 現在の検索条件タグを表示 -->
    <?php if (!empty($conditions) || !empty($search_query)): ?>
    <div class="current-filters">
        <h4>現在の検索条件：</h4>
        <div class="filter-tags">
            <?php
            // キーワード検索条件を表示
            if (!empty($search_query)) {
                echo '<div class="filter-tag">';
                echo '<span class="filter-label">キーワード:</span> ';
                echo esc_html($search_query);
                // クエリから検索語のみ削除したURLを生成
                $current_url = $_SERVER['REQUEST_URI'];
                $remove_url = remove_query_arg('s', $current_url);
                echo '<a href="' . esc_url($remove_url) . '" class="remove-filter">&times;</a>';
                echo '</div>';
            }
            
            // エリア
            if (!empty($location_slug)) {
                $location_term = get_term_by('slug', $location_slug, 'job_location');
                if ($location_term) {
                    // 現在のURLから特定のパラメータのみ削除する
                    $current_url = $_SERVER['REQUEST_URI'];
                    // LocationパラメータをURLから削除するための正規表現
                    $pattern = '/\/location\/[^\/]+/';
                    $remove_url = preg_replace($pattern, '', $current_url);
                    // 連続するスラッシュを単一のスラッシュに置換
                    $remove_url = preg_replace('/\/+/', '/', $remove_url);
                    // URLが/jobs/で終わる場合は、保持
                    if ($remove_url === '/jobs') {
                        $remove_url = '/jobs/';
                    }
                    
                    echo '<div class="filter-tag">';
                    echo '<span class="filter-label">エリア:</span> ';
                    echo esc_html($location_term->name);
                    echo '<a href="' . esc_url(home_url($remove_url)) . '" class="remove-filter">&times;</a>';
                    echo '</div>';
                }
            }
        
        // 職種
        if (!empty($position_slug)) {
            $position_term = get_term_by('slug', $position_slug, 'job_position');
            if ($position_term) {
                // 現在のURLから特定のパラメータのみ削除する
                $current_url = $_SERVER['REQUEST_URI'];
                // Positionパラメータを削除
                $pattern = '/\/position\/[^\/]+/';
                $remove_url = preg_replace($pattern, '', $current_url);
                // 連続するスラッシュを単一のスラッシュに置換
                $remove_url = preg_replace('/\/+/', '/', $remove_url);
                // URLが/jobs/で終わる場合は、保持
                if ($remove_url === '/jobs') {
                    $remove_url = '/jobs/';
                }
                
                echo '<div class="filter-tag">';
                echo '<span class="filter-label">職種:</span> ';
                echo esc_html($position_term->name);
                echo '<a href="' . esc_url(home_url($remove_url)) . '" class="remove-filter">&times;</a>';
                echo '</div>';
            }
        }
        
        // 雇用形態
        if (!empty($job_type_slug)) {
            $job_type_term = get_term_by('slug', $job_type_slug, 'job_type');
            if ($job_type_term) {
                // 現在のURLから特定のパラメータのみ削除する
                $current_url = $_SERVER['REQUEST_URI'];
                // Typeパラメータを削除
                $pattern = '/\/type\/[^\/]+/';
                $remove_url = preg_replace($pattern, '', $current_url);
                // 連続するスラッシュを単一のスラッシュに置換
                $remove_url = preg_replace('/\/+/', '/', $remove_url);
                // URLが/jobs/で終わる場合は、保持
                if ($remove_url === '/jobs') {
                    $remove_url = '/jobs/';
                }
                
                echo '<div class="filter-tag">';
                echo '<span class="filter-label">雇用形態:</span> ';
                echo esc_html($job_type_term->name);
                echo '<a href="' . esc_url(home_url($remove_url)) . '" class="remove-filter">&times;</a>';
                echo '</div>';
            }
        }
        
        // 施設形態
        if (!empty($facility_type_slug)) {
            $facility_type_term = get_term_by('slug', $facility_type_slug, 'facility_type');
            if ($facility_type_term) {
                // 現在のURLから特定のパラメータのみ削除する
                $current_url = $_SERVER['REQUEST_URI'];
                // Facilityパラメータを削除
                $pattern = '/\/facility\/[^\/]+/';
                $remove_url = preg_replace($pattern, '', $current_url);
                // 連続するスラッシュを単一のスラッシュに置換
                $remove_url = preg_replace('/\/+/', '/', $remove_url);
                // URLが/jobs/で終わる場合は、保持
                if ($remove_url === '/jobs') {
                    $remove_url = '/jobs/';
                }
                
                echo '<div class="filter-tag">';
                echo '<span class="filter-label">施設形態:</span> ';
                echo esc_html($facility_type_term->name);
                echo '<a href="' . esc_url(home_url($remove_url)) . '" class="remove-filter">&times;</a>';
                echo '</div>';
            }
        }
        
        // 特徴（単一または複数）
        if (!empty($feature_slugs)) {
            foreach ($feature_slugs as $slug) {
                $feature_term = get_term_by('slug', $slug, 'job_feature');
                if ($feature_term) {
                    // クエリパラメータから特定の特徴のみを削除
                    $current_url = $_SERVER['REQUEST_URI'];
                    $query_string = parse_url($current_url, PHP_URL_QUERY);
                    
                    if ($query_string) {
                        // クエリ文字列がある場合（features[]パラメータがある）
                        parse_str($query_string, $query_params);
                        if (isset($query_params['features']) && is_array($query_params['features'])) {
                            // 削除したい特徴を配列から除外
                            $query_params['features'] = array_diff($query_params['features'], array($slug));
                            // 新しいクエリ文字列を構築
                            $new_query_string = http_build_query($query_params);
                            $path = strtok($current_url, '?');
                            $remove_url = empty($query_params['features']) ? $path : $path . '?' . $new_query_string;
                        } else {
                            $remove_url = $current_url;
                        }
                    } else {
                        // クエリ文字列がない場合（単一特徴パラメータの場合）
                        // Featureパラメータを削除
                        $pattern = '/\/feature\/[^\/]+/';
                        $remove_url = preg_replace($pattern, '', $current_url);
                        // 連続するスラッシュを単一のスラッシュに置換
                        $remove_url = preg_replace('/\/+/', '/', $remove_url);
                        // URLが/jobs/で終わる場合は、保持
                        if ($remove_url === '/jobs') {
                            $remove_url = '/jobs/';
                        }
                    }
                    
                    echo '<div class="filter-tag">';
                    echo '<span class="filter-label">特徴:</span> ';
                    echo esc_html($feature_term->name);
                    echo '<a href="' . esc_url(home_url($remove_url)) . '" class="remove-filter">&times;</a>';
                    echo '</div>';
                }
            }
        }
        ?>
    </div>
</div>
<?php endif; ?>
            
            <!-- 検索フォームを表示 -->
            <?php get_template_part('search-form'); ?>
        </div>
        
        <div class="job-list">
            <?php if (have_posts()): ?>
                <?php while (have_posts()): the_post(); 
                    // カスタムフィールドデータの取得
                    $facility_name = get_post_meta(get_the_ID(), 'facility_name', true);
                    $facility_company = get_post_meta(get_the_ID(), 'facility_company', true);
                    $job_content_title = get_post_meta(get_the_ID(), 'job_content_title', true);
                    $salary_range = get_post_meta(get_the_ID(), 'salary_range', true);
                    $facility_address = get_post_meta(get_the_ID(), 'facility_address', true);
                    
                    // タクソノミーの取得
                    $facility_types = get_the_terms(get_the_ID(), 'facility_type');
                    $job_features = get_the_terms(get_the_ID(), 'job_feature');
                    $job_types = get_the_terms(get_the_ID(), 'job_type');
                    $job_positions = get_the_terms(get_the_ID(), 'job_position');
                    
                    // 施設形態のチェック
                    $has_jidou = false;    // 児童発達支援フラグ
                    $has_houkago = false;  // 放課後等デイサービスフラグ

                    if ($facility_types && !is_wp_error($facility_types)) {
                        foreach ($facility_types as $type) {
                            // 組み合わせタイプのチェック
                            if ($type->slug === 'jidou-houkago') {
                                // 児童発達支援・放課後等デイの場合は両方表示
                                $has_jidou = true;
                                $has_houkago = true;
                            } 
                            // 児童発達支援のみのチェック
                            else if ($type->slug === 'jidou') {
                                $has_jidou = true;
                            } 
                            // 放課後等デイサービスのみのチェック
                            else if ($type->slug === 'houkago') {
                                $has_houkago = true;
                            }
                            
                            // 従来の拡張スラッグもサポート（必要に応じて）
                            else if (in_array($type->slug, ['jidou-hattatsu', 'jidou-hattatsu-shien', 'child-development-support'])) {
                                $has_jidou = true;
                            }
                            else if (in_array($type->slug, ['houkago-day', 'houkago-dayservice', 'after-school-day-service'])) {
                                $has_houkago = true;
                            }
                        }
                    }
                    
                  // 雇用形態に基づくカラークラスを設定
$employment_color_class = 'other'; // デフォルトはその他
if ($job_types && !is_wp_error($job_types)) {
    // スラッグによる判定
    $job_type_slug = $job_types[0]->slug;
    $job_type_name = $job_types[0]->name;
    
    // スラッグベースでの判定
    switch($job_type_slug) {
        case 'full-time':
        case 'seishain': // 正社員
            $employment_color_class = 'full-time';
            break;
        case 'part-time':
        case 'part':
        case 'arubaito': // パート・アルバイト
            $employment_color_class = 'part-time';
            break;
        default:
            // スラッグで判定できない場合は名前で判定
            if ($job_type_name === '正社員') {
                $employment_color_class = 'full-time';
            } else if ($job_type_name === 'パート・アルバイト' || 
                      strpos($job_type_name, 'パート') !== false || 
                      strpos($job_type_name, 'アルバイト') !== false) {
                $employment_color_class = 'part-time';
            } else {
                $employment_color_class = 'other';
            }
            break;
    }
}
                ?>
                
                <div class="job-card">
                    <!-- 上部コンテンツ：左右に分割 -->
                    <div class="job-content">
                        <!-- 左側：サムネイル画像、施設形態アイコン、特徴タグ -->
                        <div class="left-content">
                            <!-- サムネイル画像 -->
                            <div class="job-image">
                                <?php if (has_post_thumbnail()): ?>
                                    <?php the_post_thumbnail('medium'); ?>
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/300x200" alt="<?php echo esc_attr($facility_name); ?>">
                                <?php endif; ?>
                            </div>
                            
                            <!-- 施設形態を画像アイコン -->
                            <div class="facility-icons">
                                <?php if ($has_houkago): ?>
                                <!-- 放デイアイコン -->
                                <div class="facility-icon">
                                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/day.webp" alt="放デイ">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($has_jidou): ?>
                                <!-- 児発支援アイコン -->
                                <div class="facility-icon red-icon">
                                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/support.webp" alt="児発支援">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 特徴タクソノミータグ - 3つまで表示 -->
                            <?php if ($job_features && !is_wp_error($job_features)): ?>
                            <div class="tags-container">
                                <?php 
                                $features_count = 0;
                                foreach ($job_features as $feature):
                                    if ($features_count < 3):
                                        // プレミアム特徴の判定（例：高収入求人など）
                                        $premium_class = (in_array($feature->slug, ['high-salary', 'bonus-available'])) ? 'premium' : '';
                                ?>
                                    <span class="tag <?php echo $premium_class; ?>"><?php echo esc_html($feature->name); ?></span>
                                <?php
                                        $features_count++;
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 右側：運営会社名、施設名、本文詳細 -->
                        <div class="right-content">
                            <!-- 会社名と雇用形態を横に並べる -->
                            <div class="company-section">
                                <span class="company-name"><?php echo esc_html($facility_company); ?></span>
                                <?php if ($job_types && !is_wp_error($job_types)): ?>
                                <div class="employment-type <?php echo $employment_color_class; ?>">
                                    <?php echo esc_html($job_types[0]->name); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 施設名を会社名の下に配置 -->
                            <h1 class="job-title"><?php echo esc_html($facility_name); ?></h1>
                            
                            <h2 class="job-subtitle"><?php echo esc_html($job_content_title); ?></h2>
                            
                            <p class="job-description">
                                <?php echo wp_trim_words(get_the_content(), 100, '...'); ?>
                            </p>
                            
                            <!-- 本文の下に区切り線を追加 -->
                            <div class="divider"></div>
                            
                            <!-- 職種、給料、住所情報 -->
                            <div class="job-info">
                                <?php if ($job_positions && !is_wp_error($job_positions)): ?>
                                <div class="info-item">
                                    <span class="info-icon"><i class="fa-solid fa-user"></i></span>
                                    <span><?php echo esc_html($job_positions[0]->name); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- 例えば、この部分を修正します -->
<div class="info-item">
    <span class="info-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
    <span>
        <?php 
            $salary_range = get_post_meta(get_the_ID(), 'salary_range', true);
            $salary_type = get_post_meta(get_the_ID(), 'salary_type', true);
            
            // 賃金形態の表示（月給/時給）
            if ($salary_type === 'MONTH') {
                echo '月給 ';
            } elseif ($salary_type === 'HOUR') {
                echo '時給 ';
            }
            
            echo esc_html($salary_range);
            
            // 円表示がなければ追加
            if (mb_strpos($salary_range, '円') === false) {
                echo '円';
            }
        ?>
    </span>
</div>
                                
                              <div class="info-item">
    <span class="info-icon"><i class="fa-solid fa-location-dot"></i></span>
    <span>
        <?php 
            $facility_address = get_post_meta(get_the_ID(), 'facility_address', true);
            
            // 郵便番号部分を削除する（〒123-4567などのパターンを検出）
            $address_without_postal = preg_replace('/〒?\s*\d{3}-\d{4}\s*/u', '', $facility_address);
            
            // 郵便番号を除いた住所を表示
            echo esc_html($address_without_postal);
        ?>
    </span>
</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 区切り線 -->
                    <div class="divider"></div>
                    
                    <!-- ボタンエリア -->
                    <div class="buttons-container">
                        <?php if (is_user_logged_in()): 
                            // お気に入り状態の確認
                            // 271行目付近
$user_id = get_current_user_id();
$favorites = get_user_meta($user_id, 'user_favorites', true); // 'job_favorites'から'user_favorites'に変更
$is_favorite = is_array($favorites) && in_array(get_the_ID(), $favorites);
                        ?>
                            <button class="keep-button <?php echo $is_favorite ? 'kept' : ''; ?>" data-job-id="<?php echo get_the_ID(); ?>">
                                <span class="star"></span>
                                <?php echo $is_favorite ? 'キープ済み' : 'キープ'; ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo home_url('/register/'); ?>" class="keep-button">
                                <span class="star"></span>キープ
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php the_permalink(); ?>" class="detail-view-button">詳細をみる</a>
                    </div>
                </div>
                <?php endwhile; ?>
                
                <!-- ページネーション -->
                <div class="pagination">
                    <?php
                    echo paginate_links(array(
                        'prev_text' => '&laquo; 前へ',
                        'next_text' => '次へ &raquo;',
                    ));
                    ?>
                </div>
                
            <?php else: ?>
                <div class="no-jobs-found">
                    <p>条件に一致する求人が見つかりませんでした。検索条件を変更して再度お試しください。</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- キープボタン用JavaScriptコード -->
<script>
jQuery(document).ready(function($) {
    // キープボタン機能
    $('.keep-button').on('click', function() {
        // リンクでない場合のみ処理（ログイン済みユーザー用）
        if (!$(this).attr('href')) {
            var jobId = $(this).data('job-id');
            var $button = $(this);
            
            // AJAXでキープ状態を切り替え
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'toggle_job_favorite',
                    job_id: jobId,
                    nonce: '<?php echo wp_create_nonce('job_favorite_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'added') {
                            $button.addClass('kept');
                            $button.html('<span class="star"><i class="fa-solid fa-star"></i></span> キープ済み');
                        } else {
                            $button.removeClass('kept');
                            $button.html('<span class="star"><i class="fa-solid fa-star"></i></span> キープ');
                        }
                    }
                }
            });
        }
    });
});
</script>



<?php get_footer(); ?>