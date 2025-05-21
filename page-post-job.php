<?php
/**
 * Template Name: 求人新規投稿ページ
 * 
 * 新しい求人を投稿するためのページテンプレート
 */

// 専用のヘッダーを読み込み 
include(get_stylesheet_directory() . '/agency-header.php'); 

// ログインチェック
if (!is_user_logged_in()) {
    // 非ログインの場合はログインページにリダイレクト
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// メディアアップローダーのスクリプトを読み込む
wp_enqueue_media();
wp_enqueue_script('jquery-ui-sortable');

// 現在のユーザー情報を取得
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// ユーザーが加盟教室（agency）の権限を持っているかチェック
$is_agency = in_array('agency', $current_user->roles);
if (!$is_agency && !current_user_can('administrator')) {
    // 権限がない場合はエラーメッセージ表示
    echo '<div class="error-message">この機能を利用する権限がありません。</div>';
    get_footer();
    exit;
}

// フォームが送信された場合の処理
if (isset($_POST['post_job']) && isset($_POST['job_nonce']) && 
    wp_verify_nonce($_POST['job_nonce'], 'post_new_job')) {
    
    // 基本情報を登録
    $job_data = array(
        'post_title' => sanitize_text_field($_POST['job_title']),
        'post_content' => wp_kses_post($_POST['job_content']),
        'post_status' => 'publish',
        'post_type' => 'job',
        'post_author' => $current_user_id
    );
    
    // 投稿を作成
    $job_id = wp_insert_post($job_data);
    
    if (!is_wp_error($job_id)) {
        // タクソノミーの登録
        // 勤務地域の更新（複数の入力フィールドから）
        $job_location_slugs = array();
        if (!empty($_POST['region_value'])) $job_location_slugs[] = sanitize_text_field($_POST['region_value']);
        if (!empty($_POST['prefecture_value'])) $job_location_slugs[] = sanitize_text_field($_POST['prefecture_value']);
        if (!empty($_POST['city_value'])) $job_location_slugs[] = sanitize_text_field($_POST['city_value']);
        
        if (!empty($job_location_slugs)) {
            wp_set_object_terms($job_id, $job_location_slugs, 'job_location');
        } else {
            wp_set_object_terms($job_id, array(), 'job_location');
        }
        
        // 職種（ラジオボタン）
        if (isset($_POST['job_position']) && !empty($_POST['job_position'])) {
            wp_set_object_terms($job_id, $_POST['job_position'], 'job_position');
        } else {
            wp_set_object_terms($job_id, array(), 'job_position');
        }
        
        // 雇用形態（ラジオボタン）
        if (isset($_POST['job_type']) && !empty($_POST['job_type'])) {
            wp_set_object_terms($job_id, $_POST['job_type'], 'job_type');
        } else {
            wp_set_object_terms($job_id, array(), 'job_type');
        }
        
        // 施設形態（ラジオボタン）
        if (isset($_POST['facility_type']) && !empty($_POST['facility_type'])) {
            wp_set_object_terms($job_id, $_POST['facility_type'], 'facility_type');
        } else {
            wp_set_object_terms($job_id, array(), 'facility_type');
        }
        
        // 求人特徴（チェックボックス）
        if (isset($_POST['job_feature'])) {
            wp_set_object_terms($job_id, $_POST['job_feature'], 'job_feature');
        } else {
            wp_set_object_terms($job_id, array(), 'job_feature');
        }
        
        // カスタムフィールドの登録
        update_post_meta($job_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
        update_post_meta($job_id, 'salary_range', sanitize_text_field($_POST['salary_range']));
        update_post_meta($job_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        update_post_meta($job_id, 'holidays', sanitize_text_field($_POST['holidays']));
        update_post_meta($job_id, 'benefits', wp_kses_post($_POST['benefits']));
        update_post_meta($job_id, 'requirements', wp_kses_post($_POST['requirements']));
        update_post_meta($job_id, 'application_process', wp_kses_post($_POST['application_process']));
        update_post_meta($job_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        
        // 施設情報の登録
        update_post_meta($job_id, 'facility_name', sanitize_text_field($_POST['facility_name']));
        update_post_meta($job_id, 'facility_tel', sanitize_text_field($_POST['facility_tel']));
        update_post_meta($job_id, 'facility_hours', sanitize_text_field($_POST['facility_hours']));
        update_post_meta($job_id, 'facility_url', esc_url_raw($_POST['facility_url']));
        update_post_meta($job_id, 'facility_company', sanitize_text_field($_POST['facility_company']));
        update_post_meta($job_id, 'facility_map', wp_kses($_POST['facility_map'], array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array()
            )
        )));
        
        // 郵便番号と詳細住所を保存
        update_post_meta($job_id, 'facility_zipcode', sanitize_text_field($_POST['facility_zipcode']));
        update_post_meta($job_id, 'facility_address_detail', sanitize_text_field($_POST['facility_address_detail']));

        // 完全な住所を組み立てて保存（後方互換性のため）
        $location_terms = wp_get_object_terms($job_id, 'job_location', array('fields' => 'all'));
        $prefecture = '';
        $city = '';

        foreach ($location_terms as $term) {
            $ancestors = get_ancestors($term->term_id, 'job_location', 'taxonomy');
            if (count($ancestors) == 2) {
                // 親が2つ（祖父が地域、親が都道府県、自身は市区町村）
                $city = $term->name;
                $prefecture_term = get_term($ancestors[0], 'job_location');
                if ($prefecture_term && !is_wp_error($prefecture_term)) {
                    $prefecture = $prefecture_term->name;
                }
                break;
            } else if (count($ancestors) == 1 && empty($prefecture)) {
                // 親が1つで都道府県の場合（市区町村が選択されていない場合）
                $prefecture = $term->name;
            }
        }

        $full_address = '〒' . $_POST['facility_zipcode'] . ' ' . $prefecture . $city . $_POST['facility_address_detail'];
        update_post_meta($job_id, 'facility_address', $full_address);
        
        // 追加フィールドの登録
        update_post_meta($job_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        update_post_meta($job_id, 'capacity', sanitize_text_field($_POST['capacity']));
        update_post_meta($job_id, 'staff_composition', wp_kses_post($_POST['staff_composition']));
        
        // 給与情報の登録
        update_post_meta($job_id, 'salary_type', sanitize_text_field($_POST['salary_type']));
        update_post_meta($job_id, 'salary_form', sanitize_text_field($_POST['salary_form']));
        update_post_meta($job_id, 'salary_min', sanitize_text_field($_POST['salary_min']));
        update_post_meta($job_id, 'salary_max', sanitize_text_field($_POST['salary_max']));
        update_post_meta($job_id, 'fixed_salary', sanitize_text_field($_POST['fixed_salary']));
        update_post_meta($job_id, 'salary_remarks', wp_kses_post($_POST['salary_remarks']));

        // 旧形式との互換性のため、salary_rangeも更新
        if ($_POST['salary_form'] === 'fixed') {
            $salary_range = sanitize_text_field($_POST['fixed_salary']);
        } else {
            $salary_range = sanitize_text_field($_POST['salary_min']) . '〜' . sanitize_text_field($_POST['salary_max']);
        }
        update_post_meta($job_id, 'salary_range', $salary_range);
        
        // サムネイル画像の処理
        if (isset($_POST['thumbnail_ids']) && is_array($_POST['thumbnail_ids'])) {
            $thumbnail_ids = array_map('intval', $_POST['thumbnail_ids']);
            
            // 複数画像IDをメタデータとして保存
            update_post_meta($job_id, 'job_thumbnail_ids', $thumbnail_ids);
            
            // 最初の画像をメインのサムネイルに設定
            if (!empty($thumbnail_ids)) {
                set_post_thumbnail($job_id, $thumbnail_ids[0]);
            } else {
                // 画像がなければサムネイルを削除
                delete_post_thumbnail($job_id);
            }
        } else {
            // 画像選択がない場合はメタデータとサムネイルを削除
            delete_post_meta($job_id, 'job_thumbnail_ids');
            delete_post_thumbnail($job_id);
        }
        
        // 仕事の一日の流れ（配列形式）
        if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
            $schedule_items = array();
            $count = count($_POST['daily_schedule_time']);
            
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_POST['daily_schedule_time'][$i])) {
                    $schedule_items[] = array(
                        'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                        'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                        'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                    );
                }
            }
            
            update_post_meta($job_id, 'daily_schedule_items', $schedule_items);
        }
        
        // 職員の声（配列形式）
        if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
            $voice_items = array();
            $count = count($_POST['staff_voice_role']);
            
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_POST['staff_voice_role'][$i])) {
                    $voice_items[] = array(
                        'image_id' => intval($_POST['staff_voice_image'][$i]),
                        'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                        'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                        'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                    );
                }
            }
            
            update_post_meta($job_id, 'staff_voice_items', $voice_items);
        }
        
        // 成功メッセージ表示と求人詳細ページへのリンク
        $success = true;
        $new_job_url = get_permalink($job_id);
    } else {
        // エラーメッセージ表示
        $error = $job_id->get_error_message();
        if (empty($error)) {
            $error = '不明なエラーが発生しました。再度お試しください。';
        }
    }
}
?>

<div class="post-job-container">
    <h1 class="page-title">新しい求人を投稿</h1>
    
    <?php if (isset($success) && $success): ?>
    <div class="success-message">
        <p>求人情報を投稿しました。</p>
        <p>
            <a href="<?php echo $new_job_url; ?>" class="btn-view">投稿した求人を確認する</a>
            <a href="<?php echo get_permalink(); ?>" class="btn-new">別の求人を投稿する</a>
            <?php
            // 下書きにするボタンを追加（nonceを含む）
            $draft_url = admin_url('admin-post.php?action=draft_job&job_id=' . $job_id . '&_wpnonce=' . wp_create_nonce('draft_job_' . $job_id));
            ?>
            <a href="<?php echo $draft_url; ?>" class="btn-draft">下書きにする</a>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error) && !empty($error)): ?>
    <div class="error-message">
        <p>エラーが発生しました: <?php echo $error; ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!isset($success) || !$success): ?>
    <form method="post" class="post-job-form" enctype="multipart/form-data">
        <?php wp_nonce_field('post_new_job', 'job_nonce'); ?>
        
        <div class="form-section">
            <h2 class="secti-title">基本情報</h2>
            
            <div class="form-row">
                <label for="job_title">求人タイトル <span class="required">*</span></label>
                <input type="text" id="job_title" name="job_title" required><span class="form-hint">【教室名】＋【募集職種】＋【雇用形態】＋【特徴】を書くことをお勧めします。<br>例：こどもプラス○○駅前教室　放課後等デイサービス指導員　パート／アルバイト　週3日～OK</span>
            </div>
            
            <div class="form-row">
                <label>サムネイル画像 <span class="required">*</span></label>
                <div id="thumbnails-container">
                    <!-- 新規投稿では初期画像はない -->
                </div>
                <button type="button" class="btn-media-upload" id="upload_thumbnails">画像を追加</button>
                <p class="form-hint">スライドで複数画像が掲載可能です。画像の順番はドラッグ&ドロップで変更できます。</p>
            </div>
            
            <div class="form-row">
                <label for="job_content_title">本文タイトル <span class="required">*</span></label>
                <input type="text" id="job_content_title" name="job_content_title" required><span class="form-hint">何を説明するか一目でわかる短文に。全角15文字程度が目安。<br>例：週休2日制・残業ほぼなし！児童発達支援管理責任者として活躍しませんか？</span>
            </div>
            
            <div class="form-row">
                <label for="job_content">本文詳細 <span class="required">*</span></label>
                <?php 
                wp_editor('', 'job_content', array(
                    'media_buttons' => true,
                    'textarea_name' => 'job_content',
                    'textarea_rows' => 10
                )); 
                ?>
                <span class="form-hint">仕事内容の詳細な説明や特徴などを入力してください。</span>
            </div>
        </div>

        <div class="form-section">
            <h2 class="secti-title">募集内容</h2>
            
            <div class="form-row">
                <label>勤務地域 <span class="required">*</span></label>
                
                <div class="location-selector">
                    <!-- 地域（親）選択 -->
                    <div class="location-level">
                        <select id="region-select" class="location-dropdown">
                            <option value="">地域を選択</option>
                            <?php 
                            // 親タームを取得
                            $parent_terms = get_terms(array(
                                'taxonomy' => 'job_location',
                                'hide_empty' => false,
                                'parent' => 0,
                            ));
                            
                            if ($parent_terms && !is_wp_error($parent_terms)) {
                                foreach ($parent_terms as $term) {
                                    echo '<option value="' . $term->term_id . '" data-slug="' . $term->slug . '">' . $term->name . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- 都道府県（子）選択 -->
                    <div class="location-level">
                        <select id="prefecture-select" class="location-dropdown" disabled>
                            <option value="">都道府県を選択</option>
                        </select>
                    </div>
                    
                    <!-- 市区町村（孫）選択 -->
                    <div class="location-level">
                        <select id="city-select" class="location-dropdown" disabled>
                            <option value="">市区町村を選択</option>
                        </select>
                    </div>
                </div>
                
                <!-- 現在選択されている地域の表示 -->
                <div class="selected-location-display">
                    <span>選択中: </span>
                    <span id="selected-region-text"></span>
                    <span id="selected-prefecture-text"></span>
                    <span id="selected-city-text"></span>
                </div>
                
                <!-- 隠しフィールドに選択値を保存 -->
                <input type="hidden" id="region-value" name="region_value" value="">
                <input type="hidden" id="prefecture-value" name="prefecture_value" value="">
                <input type="hidden" id="city-value" name="city_value" value="">
            </div>
            
            <div class="form-row">
                <label>職種 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_position_terms = get_terms(array(
                        'taxonomy' => 'job_position',
                        'hide_empty' => false,
                    ));
                    
                    if ($job_position_terms && !is_wp_error($job_position_terms)) {
                        foreach ($job_position_terms as $term) {
                            echo '<label class="radio-label">';
                            echo '<input type="radio" name="job_position[]" value="' . $term->slug . '">';
                            echo $term->name;
                            echo '</label>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <div class="form-row">
                <label>雇用形態 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_type_terms = get_terms(array(
                        'taxonomy' => 'job_type',
                        'hide_empty' => false,
                    ));
                    
                    if ($job_type_terms && !is_wp_error($job_type_terms)) {
                        foreach ($job_type_terms as $term) {
                            echo '<label class="radio-label">';
                            echo '<input type="radio" name="job_type[]" value="' . $term->slug . '">';
                            echo $term->name;
                            echo '</label>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <div class="form-row">
                <label for="requirements">応募要件 <span class="required">*</span></label>
                <textarea id="requirements" name="requirements" rows="5" required></textarea>
                <span class="form-hint">必要な資格や経験など</span>
            </div>
            
            <div class="form-row">
                <label for="working_hours">勤務時間 <span class="required">*</span></label>
                <textarea id="working_hours" name="working_hours" rows="3" required></textarea>
                <span class="form-hint">例: 9:00〜18:00（休憩60分）</span>
            </div>
            
            <div class="form-row">
                <label for="holidays">休日・休暇 <span class="required">*</span></label>
                <textarea id="holidays" name="holidays" rows="3" required></textarea>
                <span class="form-hint">例: 土日祝、年末年始、有給休暇あり</span>
            </div>
            
            <div class="form-row">
                <label for="benefits">福利厚生 <span class="required">*</span></label>
                <textarea id="benefits" name="benefits" rows="5" required></textarea>
                <span class="form-hint">社会保険、交通費支給、各種手当など</span>
            </div>
            
            <div class="form-row">
                <label for="salary_type">賃金形態 <span class="required">*</span></label>
                <select id="salary_type" name="salary_type" required>
                    <option value="MONTH" selected>月給</option>
                    <option value="HOUR">時給</option>
                </select>
            </div>
            
            <div class="form-row">
                <label>給与形態 <span class="required">*</span></label>
                <div class="radio-wrapper">
                    <label>
                        <input type="radio" name="salary_form" value="fixed" required> 
                        給与に幅がない（固定給）
                    </label>
                    <label>
                        <input type="radio" name="salary_form" value="range" checked required> 
                        給与に幅がある（範囲給）
                    </label>
                </div>
            </div>
            
            <div id="fixed-salary-field" class="form-row salary-field" style="display: none;">
                <label for="fixed_salary">給与（固定給） <span class="required">*</span></label>
                <input type="text" id="fixed_salary" name="fixed_salary">
                <span class="form-hint">例: 250,000円</span>
            </div>
            
            <div id="range-salary-fields" class="salary-field">
                <div class="form-row">
                    <label for="salary_min">給与①最低賃金 <span class="required">*</span></label>
                    <input type="text" id="salary_min" name="salary_min" required>
                    <span class="form-hint">例: 200,000円</span>
                </div>
                
                <div class="form-row">
                    <label for="salary_max">給与②最高賃金 <span class="required">*</span></label>
                    <input type="text" id="salary_max" name="salary_max" required>
                    <span class="form-hint">例: 300,000円</span>
                </div>
            </div>
            
            <div class="form-row">
                <label for="salary_remarks">給料についての備考</label>
                <textarea id="salary_remarks" name="salary_remarks" rows="3"></textarea>
                <span class="form-hint">例: 経験・能力により優遇。試用期間3ヶ月あり（同条件）。</span>
            </div>
            
            <!-- 旧方式との互換性のため、salary_rangeも保持します -->
            <input type="hidden" id="salary_range" name="salary_range">

            <div class="form-row">
                <label for="bonus_raise">昇給・賞与</label>
                <textarea id="bonus_raise" name="bonus_raise" rows="5"></textarea>
                <span class="form-hint">昇給制度や賞与の詳細など</span>
            </div>
            
            <div class="form-row">
                <label for="application_process">選考プロセス</label>
                <textarea id="application_process" name="application_process" rows="5"></textarea>
                <span class="form-hint">書類選考、面接回数など</span>
            </div>
            
            <div class="form-row">
                <label for="contact_info">応募方法・連絡先 <span class="required">*</span></label>
                <textarea id="contact_info" name="contact_info" rows="5" required></textarea>
                <span class="form-hint">電話番号、メールアドレス、応募フォームURLなど</span>
            </div>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">求人の特徴</h2>
            
            <?php 
            // 親の特徴タグを取得
            $parent_feature_terms = get_terms(array(
                'taxonomy' => 'job_feature',
                'hide_empty' => false,
                'parent' => 0
            ));
            
            if ($parent_feature_terms && !is_wp_error($parent_feature_terms)) {
                echo '<div class="feature-accordion-container">';
                foreach ($parent_feature_terms as $parent_term) {
                    echo '<div class="feature-accordion">';
                    echo '<div class="feature-accordion-header">';
                    echo '<h3>' . $parent_term->name . '</h3>';
                    echo '<span class="accordion-icon">+</span>';
                    echo '</div>';
                    
                    // 子の特徴タグを取得
                    $child_feature_terms = get_terms(array(
                        'taxonomy' => 'job_feature',
                        'hide_empty' => false,
                        'parent' => $parent_term->term_id
                    ));
                    
                    if ($child_feature_terms && !is_wp_error($child_feature_terms)) {
                        echo '<div class="feature-accordion-content">';
                        echo '<div class="taxonomy-select">';
                        foreach ($child_feature_terms as $term) {
                            echo '<label class="checkbox-label feature-label">';
                            echo '<input type="checkbox" name="job_feature[]" value="' . $term->slug . '">';
                            echo $term->name;
                            echo '</label>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">職場の環境</h2>
            
            <div class="form-row">
                <label>仕事の一日の流れ</label>
                <div id="daily-schedule-container">
                    <div class="daily-schedule-item">
                        <div class="schedule-time">
                            <label>時間</label>
                            <input type="text" name="daily_schedule_time[]" placeholder="9:00">
                        </div>
                        <div class="schedule-title">
                            <label>タイトル</label>
                            <input type="text" name="daily_schedule_title[]" placeholder="出社・朝礼">
                        </div>
                        <div class="schedule-description">
                            <label>詳細</label>
                            <textarea name="daily_schedule_description[]" rows="3" placeholder="出社して業務の準備をします。朝礼で1日の予定を確認します。"></textarea>
                        </div>
                        <button type="button" class="remove-schedule-item" style="display:none;">削除</button>
                    </div>
                </div>
                <button type="button" id="add-schedule-item" class="btn-add-item">時間枠を追加</button>
            </div>
            
            <div class="form-row">
                <label>職員の声</label>
                <div id="staff-voice-container">
                    <div class="staff-voice-item">
                        <div class="voice-image">
                            <label>サムネイル</label>
                            <div class="voice-image-preview"></div>
                            <input type="hidden" name="staff_voice_image[]" value="">
                            <button type="button" class="upload-voice-image">画像を選択</button>
                            <button type="button" class="remove-voice-image" style="display:none;">削除</button>
                        </div>
                        <div class="voice-role">
                            <label>職種</label>
                            <input type="text" name="staff_voice_role[]" placeholder="保育士">
                        </div>
                        <div class="voice-years">
                            <label>勤続年数</label>
                            <input type="text" name="staff_voice_years[]" placeholder="3年目">
                        </div>
                        <div class="voice-comment">
                            <label>コメント</label>
                            <textarea name="staff_voice_comment[]" rows="4" placeholder="職場の雰囲気や働きやすさについてのコメント"></textarea>
                        </div>
                        <button type="button" class="remove-voice-item" style="display:none;">削除</button>
                    </div>
                </div>
                <button type="button" id="add-voice-item" class="btn-add-item">職員の声を追加</button>
            </div>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">事業所の情報</h2>
            
            <div class="form-row">
                <label for="facility_name">施設名 <span class="required">*</span></label>
                <input type="text" id="facility_name" name="facility_name" required>
            </div>
            
            <div class="form-row">
                <label for="facility_company">運営会社名 <span class="required">*</span></label>
                <input type="text" id="facility_company" name="facility_company" required>
            </div>
            
            <div class="form-row">
                <label for="facility_address">施設住所 <span class="required">*</span></label>
                
                <div class="address-container">
                    <div class="address-row">
                        <label for="facility_zipcode">郵便番号</label>
                        <input type="text" id="facility_zipcode" name="facility_zipcode" placeholder="123-4567">
                    </div>
                    
                    <div class="address-row">
                        <label>都道府県・市区町村</label>
                        <div id="location_display" class="location-display">
                            <span class="location-empty">タクソノミーから選択されます</span>
                        </div>
                        <p class="form-hint">※ 「勤務地域」セクションで選択した都道府県・市区町村が反映されます</p>
                    </div>
                    
                    <div class="address-row">
                        <label for="facility_address_detail">町名番地・ビル名</label>
                        <input type="text" id="facility_address_detail" name="facility_address_detail" placeholder="○○町1-2-3 △△ビル5階" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <label for="facility_map">GoogleMap <span class="required">*</span></label>
                <textarea id="facility_map" name="facility_map" rows="5" required placeholder="GoogleMapの埋め込みコードを貼り付けてください"></textarea>
                <span class="form-hint">GoogleMapの「共有」から「地図を埋め込む」を選択して、埋め込みコードをコピーして貼り付けてください。</span>
            </div>
            
            <div class="form-row">
                <label>施設形態 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $facility_type_terms = get_terms(array(
                        'taxonomy' => 'facility_type',
                        'hide_empty' => false,
                    ));
                    
                    if ($facility_type_terms && !is_wp_error($facility_type_terms)) {
                        foreach ($facility_type_terms as $term) {
                            echo '<label class="radio-label">';
                            echo '<input type="radio" name="facility_type[]" value="' . $term->slug . '">';
                            echo $term->name;
                            echo '</label>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <div class="form-row">
                <label for="capacity">利用者定員数</label>
                <input type="text" id="capacity" name="capacity">
                <span class="form-hint">例: 20名</span>
            </div>
            
            <div class="form-row">
                <label for="staff_composition">スタッフ構成</label>
                <textarea id="staff_composition" name="staff_composition" rows="4"></textarea>
                <span class="form-hint">例: 児童発達支援管理責任者1名、指導員2名、保育士4名、送迎スタッフ2名、事務員1名</span>
            </div>
            
            <div class="form-row">
                <label for="facility_tel">施設電話番号</label>
                <input type="text" id="facility_tel" name="facility_tel">
            </div>
            
            <div class="form-row">
                <label for="facility_hours">施設営業時間</label>
                <textarea id="facility_hours" name="facility_hours" rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <label for="facility_url">施設WebサイトURL</label>
                <input type="url" id="facility_url" name="facility_url">
            </div>
        </div>
        
        <div class="form-actions">
            <input type="submit" name="post_job" value="求人情報を投稿する" class="btn-submit">
            <a href="<?php echo home_url('/job-list/'); ?>" class="btn-cancel">キャンセル</a>
        </div>
    </form>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script>
    jQuery(document).ready(function($) {
        // 複数サムネイル画像用のメディアアップローダー
        $('#upload_thumbnails').click(function(e) {
            e.preventDefault();
            
            var custom_uploader = wp.media({
                title: '求人サムネイル画像を選択',
                button: {
                    text: '画像を選択'
                },
                multiple: true // 複数選択可能に変更
            });
            
            custom_uploader.on('select', function() {
                var attachments = custom_uploader.state().get('selection').toJSON();
                
                // 選択された各画像を処理
                $.each(attachments, function(index, attachment) {
                    var $thumbnailItem = $('<div class="thumbnail-item"></div>');
                    $thumbnailItem.append('<div class="thumbnail-preview"><img src="' + attachment.url + '" alt="サムネイル画像"></div>');
                    $thumbnailItem.append('<input type="hidden" name="thumbnail_ids[]" value="' + attachment.id + '">');
                    $thumbnailItem.append('<button type="button" class="remove-thumbnail-btn">削除</button>');
                    
                    // サムネイルコンテナに追加
                    $('#thumbnails-container').append($thumbnailItem);
                });
            });
            
            custom_uploader.open();
        });
        
        // サムネイルの削除処理
        $(document).on('click', '.remove-thumbnail-btn', function() {
            $(this).closest('.thumbnail-item').remove();
        });
        
        // サムネイルの並び替え機能（jQuery UIが必要）
        if ($.fn.sortable) {
            $('#thumbnails-container').sortable({
                placeholder: 'ui-state-highlight',
                update: function(event, ui) {
                    // 並び替え後の処理（必要であれば）
                }
            });
            $('#thumbnails-container').disableSelection();
        }
        
        // 給与フィールドの表示切替
        $('input[name="salary_form"]').on('change', function() {
            // すべての給与フィールドを非表示
            $('.salary-field').hide();
            
            // 選択された給与形態に応じたフィールドを表示
            if ($(this).val() === 'fixed') {
                $('#fixed-salary-field').show();
                $('#fixed_salary').prop('required', true);
                $('#salary_min, #salary_max').prop('required', false);
            } else {
                $('#range-salary-fields').show();
                $('#fixed_salary').prop('required', false);
                $('#salary_min, #salary_max').prop('required', true);
            }
        });
        
        // ページ読み込み時の初期状態設定
        $('input[name="salary_form"]:checked').trigger('change');
        
        // 地域（親）選択時の処理
        $('#region-select').on('change', function() {
            var regionId = $(this).val();
            var regionText = $(this).find('option:selected').text();
            var regionSlug = $(this).find('option:selected').data('slug');
            
            // 選択表示を更新
            $('#selected-region-text').text(regionText !== '地域を選択' ? regionText : '');
            $('#selected-prefecture-text').text('');
            $('#selected-city-text').text('');
            
            // 隠しフィールドを更新
            $('#region-value').val(regionSlug || '');
            $('#prefecture-value').val('');
            $('#city-value').val('');
            
            // 都道府県・市区町村セレクトをリセット
            $('#prefecture-select').html('<option value="">都道府県を選択</option>').prop('disabled', true);
            $('#city-select').html('<option value="">市区町村を選択</option>').prop('disabled', true);
            
            if (regionId) {
                // 都道府県を読み込み
                $('#prefecture-select').prop('disabled', true).html('<option value="">読み込み中...</option>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'get_taxonomy_children',
                        taxonomy: 'job_location',
                        parent_id: regionId,
                        _wpnonce: '<?php echo wp_create_nonce("get_taxonomy_children"); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value="">都道府県を選択</option>';
                            $.each(response.data, function(index, term) {
                                options += '<option value="' + term.term_id + '" data-slug="' + term.slug + '">' + term.name + '</option>';
                            });
                            $('#prefecture-select').html(options).prop('disabled', false);
                        } else {
                            $('#prefecture-select').html('<option value="">都道府県がありません</option>').prop('disabled', true);
                        }
                        
                        // 住所表示も更新
                        updateLocationDisplay();
                    },
                    error: function(xhr, status, error) {
                        $('#prefecture-select').html('<option value="">エラーが発生しました</option>').prop('disabled', true);
                        updateLocationDisplay();
                    }
                });
            } else {
                // 地域が選択されていない場合も住所表示を更新
                updateLocationDisplay();
            }
        });
        
        // 都道府県（子）選択時の処理
        $('#prefecture-select').on('change', function() {
            var prefectureId = $(this).val();
            var prefectureText = $(this).find('option:selected').text();
            var prefectureSlug = $(this).find('option:selected').data('slug');
            
            // 選択表示を更新
            $('#selected-prefecture-text').text(prefectureText !== '都道府県を選択' && prefectureText !== '読み込み中...' && prefectureText !== '都道府県がありません' && prefectureText !== 'エラーが発生しました' ? ' > ' + prefectureText : '');
            $('#selected-city-text').text('');
            
            // 隠しフィールドを更新
            $('#prefecture-value').val(prefectureSlug || '');
            $('#city-value').val('');
            
            // 市区町村セレクトをリセット
            $('#city-select').html('<option value="">市区町村を選択</option>').prop('disabled', true);
            
            if (prefectureId) {
                // 市区町村を読み込み
                $('#city-select').prop('disabled', true).html('<option value="">読み込み中...</option>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'get_taxonomy_children',
                        taxonomy: 'job_location',
                        parent_id: prefectureId,
                        _wpnonce: '<?php echo wp_create_nonce("get_taxonomy_children"); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value="">市区町村を選択</option>';
                            $.each(response.data, function(index, term) {
                                options += '<option value="' + term.term_id + '" data-slug="' + term.slug + '">' + term.name + '</option>';
                            });
                            $('#city-select').html(options).prop('disabled', false);
                        } else {
                            $('#city-select').html('<option value="">市区町村がありません</option>').prop('disabled', true);
                        }
                        
                        // 住所表示も更新
                        updateLocationDisplay();
                    },
                    error: function(xhr, status, error) {
                        $('#city-select').html('<option value="">エラーが発生しました</option>').prop('disabled', true);
                        updateLocationDisplay();
                    }
                });
            } else {
                // 都道府県が選択されていない場合も住所表示を更新
                updateLocationDisplay();
            }
        });
        
        // 市区町村（孫）選択時の処理
        $('#city-select').on('change', function() {
            var cityText = $(this).find('option:selected').text();
            var citySlug = $(this).find('option:selected').data('slug');
            
            // 選択表示を更新
            $('#selected-city-text').text(cityText !== '市区町村を選択' && cityText !== '読み込み中...' && cityText !== '市区町村がありません' && cityText !== 'エラーが発生しました' ? ' > ' + cityText : '');
            
            // 隠しフィールドを更新
            $('#city-value').val(citySlug || '');
            
            // 住所表示も即時更新
            updateLocationDisplay();
        });
        
        // 勤務地域（都道府県・市区町村）の選択を住所表示に反映
        function updateLocationDisplay() {
            // プルダウンから選択された値
            var prefectureText = $('#prefecture-select option:selected').text();
            var cityText = $('#city-select option:selected').text();
            
            // 無効な値かチェック
            var invalidValues = ['都道府県を選択', '読み込み中...', '都道府県がありません', 'エラーが発生しました'];
            var invalidCityValues = ['市区町村を選択', '読み込み中...', '市区町村がありません', 'エラーが発生しました'];
            
            if (invalidValues.includes(prefectureText)) {
                prefectureText = '';
            }
            
            if (invalidCityValues.includes(cityText)) {
                cityText = '';
            }
            
            var displayText = '';
            if (prefectureText) {
                displayText = prefectureText;
                if (cityText) {
                    displayText += ' ' + cityText;
                }
            }
            
            // 施設情報の都道府県・市区町村表示を更新
            if (displayText) {
                $('#location_display').text(displayText);
            } else {
                $('#location_display').html('<span class="location-empty">タクソノミーから選択されます</span>');
            }
        }
        
        // 特徴タグのアコーディオン機能
        $('.feature-accordion-header').on('click', function() {
            var $accordion = $(this).parent();
            var $content = $accordion.find('.feature-accordion-content');
            var $icon = $(this).find('.accordion-icon');
            
            if ($content.is(':visible')) {
                $content.slideUp();
                $icon.text('+');
            } else {
                $content.slideDown();
                $icon.text('-');
            }
        });
        
        // 仕事の一日の流れの項目を追加
        $('#add-schedule-item').on('click', function() {
            var newItem = $('.daily-schedule-item:first').clone();
            newItem.find('input, textarea').val('');
            newItem.find('.remove-schedule-item').show();
            $('#daily-schedule-container').append(newItem);
        });
        
        // 仕事の一日の流れの項目を削除
        $(document).on('click', '.remove-schedule-item', function() {
            $(this).closest('.daily-schedule-item').remove();
        });
        
        // 職員の声の項目を追加
        $('#add-voice-item').on('click', function() {
            var newItem = $('.staff-voice-item:first').clone();
            newItem.find('input, textarea').val('');
            newItem.find('.voice-image-preview').empty();
            newItem.find('.remove-voice-item').show();
            $('#staff-voice-container').append(newItem);
        });
        
        // 職員の声の項目を削除
        $(document).on('click', '.remove-voice-item', function() {
            $(this).closest('.staff-voice-item').remove();
        });
        
        // 職員の声の画像アップローダー
        $(document).on('click', '.upload-voice-image', function() {
            var button = $(this);
            var imageContainer = button.closest('.voice-image');
            var previewContainer = imageContainer.find('.voice-image-preview');
            var inputField = imageContainer.find('input[name^="staff_voice_image"]');
            
            var custom_uploader = wp.media({
                title: '職員の声の画像を選択',
                button: {
                    text: '画像を選択'
                },
                multiple: false
            });
            
            custom_uploader.on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                previewContainer.html('<img src="' + attachment.url + '" alt="スタッフ画像">');
                inputField.val(attachment.id);
                
                // 削除ボタンを表示
                imageContainer.find('.remove-voice-image').show();
            });
            
            custom_uploader.open();
        });
        
        // 職員の声の画像削除
        $(document).on('click', '.remove-voice-image', function() {
            var imageContainer = $(this).closest('.voice-image');
            imageContainer.find('.voice-image-preview').empty();
            imageContainer.find('input[name^="staff_voice_image"]').val('');
            $(this).hide();
        });
    });
    </script>

    <style>
    /* 複数サムネイルのスタイル */
    #thumbnails-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .thumbnail-item {
        position: relative;
        width: 150px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: #f9f9f9;
    }
    
    .thumbnail-preview {
        width: 100%;
        height: 120px;
        margin-bottom: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .thumbnail-preview img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
    }
    
    .remove-thumbnail-btn {
        width: 100%;
        padding: 4px;
        background-color: #f44336;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .remove-thumbnail-btn:hover {
        background-color: #d32f2f;
    }
    
    /* ドラッグ&ドロップ用のスタイル */
    .ui-state-highlight {
        width: 150px;
        height: 165px;
        border: 2px dashed #2196F3;
        background-color: #E3F2FD;
    }
    
    /* コンパクトな勤務地域選択のスタイル */
    .location-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .location-level {
        flex: 1;
        min-width: 150px;
    }
    
    .location-dropdown {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #fff;
    }
    
    .location-dropdown:disabled {
        background-color: #f5f5f5;
        cursor: not-allowed;
    }
    
    .selected-location-display {
        margin-top: 5px;
        padding: 8px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .selected-location-display span {
        display: inline-block;
    }
    
    /* 特徴タグのアコーディオンスタイル */
    .feature-accordion-container {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .feature-accordion {
        margin-bottom: 1px;
    }
    
    .feature-accordion:last-child {
        margin-bottom: 0;
    }
    
    .feature-accordion-header {
        background-color: #f7f7f7;
        padding: 10px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .feature-accordion-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
    }
    
    .accordion-icon {
        font-size: 18px;
        font-weight: bold;
    }
    
    .feature-accordion-content {
        padding: 15px;
        background-color: #fff;
        display: none;
    }
    
    /* チェックボックスのスタイル強化 */
    .feature-accordion .taxonomy-select {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .feature-accordion .checkbox-label {
        flex: 0 0 auto;
        margin: 0;
    }

    /* ラジオボタンのスタイル */
    .radio-label {
        display: inline-block;
        margin: 5px;
        padding: 6px 12px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .radio-label input {
        margin-right: 5px;
    }
    
    /* 下書きにするボタン */
    .btn-draft {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        margin-right: 10px;
        background-color: #ffb74d;
        color: white;
        border: none;
    }
    
    .btn-draft:hover {
        background-color: #ff9800;
    }

    /* 施設住所のスタイル */
    .address-container {
        margin-bottom: 15px;
    }
    
    .address-row {
        margin-bottom: 10px;
    }
    
    .address-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: normal;
    }
    
    #facility_zipcode {
        width: 150px;
    }
    
    .location-display {
        padding: 8px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-height: 20px;
    }
    
    .location-empty {
        color: #999;
        font-style: italic;
    }

    /* モバイル対応 */
    @media (max-width: 768px) {
        .location-selector {
            flex-direction: column;
        }
        
        .location-level {
            width: 100%;
        }
    }

    /* 求人投稿フォームのスタイル */
    .post-job-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-title {
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .success-message {
        background-color: #e8f5e9;
        color: #2e7d32;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .error-message {
        background-color: #ffebee;
        color: #c62828;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .form-section {
        margin-bottom: 30px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 20px;
        background-color: #fff;
    }
    
    .secti-title {
        font-size: 18px;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .form-row {
        margin-bottom: 20px;
    }
    
    .form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-row input[type="text"],
    .form-row input[type="url"],
    .form-row textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    .form-hint {
        display: block;
        font-size: 12px;
        color: #757575;
        margin-top: 5px;
    }
    
    .required {
        color: #f44336;
    }
    
    .taxonomy-select {
        display: flex;
        flex-wrap: wrap;
        margin: -5px;
    }
    
    .checkbox-label {
        display: inline-block;
        margin: 5px;
        padding: 6px 12px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .checkbox-label input {
        margin-right: 5px;
    }
    
    .feature-label {
        background-color: #e3f2fd;
    }
    
    .thumbnail-preview, .voice-image-preview {
        margin-bottom: 10px;
    }
    
    .thumbnail-preview img, .voice-image-preview img {
        max-width: 200px;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 2px;
    }
    
    .btn-media-upload,
    .btn-media-remove,
    .btn-submit,
    .btn-cancel,
    .btn-view,
    .btn-new,
    .btn-add-item,
    .upload-voice-image,
    .remove-voice-image,
    .remove-schedule-item,
    .remove-voice-item {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        margin-right: 10px;
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }
    
    .btn-media-remove,
    .remove-voice-image,
    .remove-schedule-item,
    .remove-voice-item {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }
    
    .btn-view {
        background-color: #2196f3;
        color: white;
        border: none;
    }
    
    .btn-new, .btn-add-item {
        background-color: #ff9800;
        color: white;
        border: none;
    }
    
    .form-actions {
        margin-top: 20px;
        text-align: center;
    }
    
    .btn-submit {
        background-color: #4caf50;
        color: white;
        border: none;
        font-size: 16px;
        padding: 10px 20px;
    }
    
    .btn-cancel {
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }
    
    /* 階層化タクソノミー用スタイル */
    .parent-term {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .parent-label {
        background-color: #e8eaf6;
        font-weight: bold;
    }
    
    .child-label {
        background-color: #f5f5f5;
    }
    
    .grandchild-label {
        background-color: #fafafa;
    }
    
    /* 一日の流れと職員の声のスタイル */
    .daily-schedule-item, .staff-voice-item {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        margin-bottom: 15px;
		background-color: #fafafa;
        position: relative;
    }
    
    .schedule-time, .schedule-title, .voice-role, .voice-years {
        display: inline-block;
        vertical-align: top;
        margin-right: 15px;
        margin-bottom: 10px;
    }
    
    .schedule-time input, .schedule-title input, .voice-role input, .voice-years input {
        width: 150px;
    }
    
    .schedule-description, .voice-comment, .voice-image {
        margin-bottom: 10px;
    }
    
    .remove-schedule-item, .remove-voice-item {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        font-size: 12px;
    }
    
    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .post-job-container {
            padding: 10px;
        }
        
        .form-section {
            padding: 15px;
        }
        
        .taxonomy-select {
            flex-direction: column;
        }
        
        .checkbox-label {
            margin: 3px 0;
        }
        
        .schedule-time, .schedule-title, .voice-role, .voice-years {
            display: block;
            margin-right: 0;
        }
    }
    </style>
</div>

<?php 
// 専用のフッターを読み込み 
include(get_stylesheet_directory() . '/agency-footer.php'); 
?>