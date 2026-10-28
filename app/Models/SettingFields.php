<?php
declare(strict_types=1);
namespace App\Models;

final class SettingFields {
    public static function groups(): array {
        return [
            'company'=>['Company Information', ['company_name'=>['Company name','text'],'founded_year'=>['Founding year (for automatic statistics)','year'],'tagline'=>['Tagline','text'],'description'=>['Company description','textarea'],'header_logo'=>['Header logo','media'],'footer_logo'=>['Footer logo','media'],'favicon'=>['Favicon','media']]],
            'contact'=>['Contact Information',['address'=>['Address','textarea'],'phone'=>['Phone numbers','text'],'email'=>['Email address','email'],'business_hours'=>['Business hours','textarea'],'map_link'=>['Map link','url'],'map_embed'=>['Map embed URL','map'],'whatsapp'=>['WhatsApp number','text'],'notification_email'=>['Enquiry notification email','email']]],
            'header'=>['Header Settings',['utility_company_label'=>['Top bar Main','text'],'utility_tagline'=>['Top bar Sub','text'],'utility_location'=>['Top bar — location / reach text','text'],'utility_phone'=>['Top bar — phone number','text'],'utility_email'=>['Top bar — email address','email'],'linkedin_url'=>['Top bar — LinkedIn profile URL','url'],'instagram_url'=>['Top bar — Instagram profile URL','url'],'whatsapp_url'=>['Top bar — WhatsApp link','url'],'facebook_url'=>['Top bar — Facebook profile URL','url'],'logo_link'=>['Logo link','url'],'alternate_logo'=>['Alternate logo','media'],'search_visible'=>['Show search button','checkbox'],'product_search'=>['Show product search','checkbox'],'cta_label'=>['Header button label','text'],'cta_url'=>['Header button link','url'],'sticky_header'=>['Sticky header','checkbox'],'mobile_navigation'=>['Mobile menu behavior','mobile']]],
            'footer'=>['Footer Management',['footer_description'=>['Company description','textarea'],'copyright'=>['Copyright text (use {year} and {company})','text'],'developer_credit'=>['Developer credit','text']]],
            'appearance'=>['Appearance & Theme',['primary_color'=>['Primary color','color'],'secondary_color'=>['Secondary color','color'],'accent_color'=>['Accent color','color'],'header_color'=>['Header background','color'],'footer_color'=>['Footer background','color'],'button_color'=>['Button color','color'],'back_to_top'=>['Show back-to-top button','checkbox'],'whatsapp_visible'=>['Show floating WhatsApp button','checkbox']]],
            'seo'=>['SEO Settings',['default_seo_title'=>['Default SEO title','text'],'default_description'=>['Default meta description','textarea'],'social_image'=>['Default social sharing image','media'],'robots_index'=>['Allow search engines to index the website','checkbox']]],
            'integrations'=>['Integrations',['analytics_id'=>['Google Analytics measurement ID (G-...)','analytics'],'turnstile_site_key'=>['Cloudflare Turnstile site key','text']]],
            'text'=>['Website Labels',[]]
        ];
    }
}
