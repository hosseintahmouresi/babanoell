<div class="products-widget-box news-box">
    <div class="product-widget-box-title">
        <?php if (isset($sectionLink) && $sectionLink) : ?>
            <h3 class="dashboard-title cursor">
                <a href="<?php echo esc_url($sectionLink) ?>"><?php echo esc_html($sectionTitle) ?></a>
            </h3>
            <a class="section-link" href="<?php echo esc_url($sectionLink) ?>"><?php _e('Show all', 'mihanpanel') ?></a>
        <?php else : ?>
            <h3 class="dashboard-title"><?php echo esc_html($sectionTitle) ?></a></h3>
        <?php endif; ?>
    </div>

    <div class="post-items-wrapper">
        <?php if (isset($prList) && $prList) : ?>
            <?php foreach ($prList as $item) : ?>
                <article class="post-item">
                    <a href="<?php echo $item->get_permalink() ?>">
                        <div class="thumbnail">
                            <?php
                            $imageID = $item->get_image_id();
                            $thumbnailUrl = wp_get_attachment_url($imageID);
                            ?>
                            <img src="<?php echo esc_attr($thumbnailUrl) ?>" alt="<?php echo esc_attr(sprintf('%s-product-thumbnail', $item->get_name())) ?>">
                        </div>
                        <header>
                            <h3><?php echo $item->get_name() ?></h3>
                        </header>
                        <div class="pr-item-body">
                            <span class="price-wrapper"><?php echo $item->get_price_html() ?></span>
                            <span class="show-more-btn"><?php _e('Buy product', 'mihanpanel') ?></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <span class="empty-section"><?php _e('No products found', 'mihanpanel') ?></span>
        <?php endif; ?>
    </div>
</div>