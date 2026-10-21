package pt.motogreen.emoto;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.net.http.SslError;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import java.util.Locale;

public class MainActivity extends Activity {
    private static final String PREFS = "emotogreen_app";
    private static final String LANGUAGE = "language";
    private FrameLayout root;
    private WebView webView;

    @Override protected void attachBaseContext(Context base) {
        String language = base.getSharedPreferences(PREFS, MODE_PRIVATE).getString(LANGUAGE, "pt");
        Locale locale = new Locale(language);
        Configuration config = new Configuration(base.getResources().getConfiguration());
        config.setLocale(locale);
        super.attachBaseContext(base.createConfigurationContext(config));
    }

    @Override protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(244, 248, 246));
        setContentView(root);
        showLauncher();
    }

    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }

    private TextView text(String value, int size, int color) {
        TextView view = new TextView(this);
        view.setText(value); view.setTextSize(size); view.setTextColor(color);
        return view;
    }

    private Button button(String label) {
        Button button = new Button(this);
        button.setText(label); button.setTextSize(18); button.setAllCaps(false);
        button.setTextColor(Color.WHITE); button.setBackgroundColor(Color.rgb(7, 99, 75));
        button.setPadding(dp(20),dp(16),dp(20),dp(16));
        return button;
    }

    private GradientDrawable rounded(int color, int radius, int strokeColor, int strokeWidth) {
        GradientDrawable background = new GradientDrawable();
        background.setColor(color); background.setCornerRadius(dp(radius));
        if (strokeWidth > 0) background.setStroke(dp(strokeWidth), strokeColor);
        return background;
    }

    private TextView languagePill(String label, boolean active) {
        TextView pill = text(label, 14, active ? Color.WHITE : Color.rgb(7,61,49));
        pill.setTypeface(Typeface.DEFAULT, Typeface.BOLD); pill.setGravity(Gravity.CENTER);
        pill.setBackground(rounded(active ? Color.rgb(7,99,75) : Color.TRANSPARENT, 18, active ? Color.TRANSPARENT : Color.rgb(206,224,217), 1));
        return pill;
    }

    private LinearLayout accessCard(String badge, String title, String description, View.OnClickListener listener) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.HORIZONTAL); card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(18),dp(17),dp(16),dp(17));
        card.setBackground(rounded(Color.WHITE,20,Color.rgb(222,233,228),1));
        card.setElevation(dp(3)); card.setClickable(true); card.setFocusable(true); card.setOnClickListener(listener);

        TextView icon = text(badge, badge.length() > 2 ? 14 : 22, Color.rgb(7,99,75));
        icon.setTypeface(Typeface.DEFAULT,Typeface.BOLD); icon.setGravity(Gravity.CENTER);
        icon.setBackground(rounded(Color.rgb(224,244,236),16,Color.TRANSPARENT,0));
        card.addView(icon,new LinearLayout.LayoutParams(dp(66),dp(66)));

        LinearLayout copy = new LinearLayout(this); copy.setOrientation(LinearLayout.VERTICAL); copy.setPadding(dp(18),0,dp(10),0);
        TextView heading=text(title,21,Color.rgb(15,39,31)); heading.setTypeface(Typeface.DEFAULT,Typeface.BOLD);
        TextView help=text(description,15,Color.rgb(100,120,112)); help.setPadding(0,dp(5),0,0);
        copy.addView(heading); copy.addView(help); card.addView(copy,new LinearLayout.LayoutParams(0,-2,1));
        TextView arrow=text("›",34,Color.rgb(7,99,75)); arrow.setGravity(Gravity.CENTER); card.addView(arrow,new LinearLayout.LayoutParams(dp(38),dp(56)));
        return card;
    }

    private void showLauncher() {
        if (webView != null) { webView.destroy(); webView = null; }
        root.removeAllViews();
        root.setBackground(rounded(Color.rgb(242,247,245),0,Color.TRANSPARENT,0));
        LinearLayout page = new LinearLayout(this);
        boolean landscape = getResources().getConfiguration().screenWidthDp >= 700;
        page.setOrientation(landscape ? LinearLayout.HORIZONTAL : LinearLayout.VERTICAL);
        page.setPadding(dp(24),dp(24),dp(24),dp(24));
        root.addView(page,new FrameLayout.LayoutParams(-1,-1));

        LinearLayout hero = new LinearLayout(this); hero.setOrientation(LinearLayout.VERTICAL); hero.setGravity(Gravity.BOTTOM);
        hero.setPadding(dp(42),dp(42),dp(42),dp(42)); hero.setBackground(rounded(Color.rgb(7,61,49),28,Color.TRANSPARENT,0));
        ImageView mark=new ImageView(this); mark.setImageResource(R.drawable.motogreen_logo); mark.setScaleType(ImageView.ScaleType.CENTER_CROP);
        mark.setPadding(dp(5),dp(5),dp(5),dp(5)); mark.setBackground(rounded(Color.WHITE,18,Color.TRANSPARENT,0)); mark.setClipToOutline(true);
        hero.addView(mark,new LinearLayout.LayoutParams(dp(84),dp(84)));
        TextView brand=text("E-Motogreen",34,Color.WHITE); brand.setTypeface(Typeface.DEFAULT,Typeface.BOLD); brand.setPadding(0,dp(26),0,dp(8)); hero.addView(brand);
        TextView welcome=text(getString(R.string.welcome),20,Color.rgb(190,224,211)); welcome.setLineSpacing(0,1.15f); hero.addView(welcome);
        TextView live=text("●  "+getString(R.string.secure_access),14,Color.rgb(126,214,180)); live.setPadding(0,dp(28),0,0); hero.addView(live);
        LinearLayout.LayoutParams heroParams = landscape ? new LinearLayout.LayoutParams(0,-1,42) : new LinearLayout.LayoutParams(-1,dp(290));
        page.addView(hero,heroParams);

        LinearLayout content = new LinearLayout(this); content.setOrientation(LinearLayout.VERTICAL); content.setGravity(Gravity.CENTER_VERTICAL);
        content.setPadding(landscape?dp(54):dp(8),landscape?dp(30):dp(32),landscape?dp(42):dp(8),dp(24));
        page.addView(content,landscape?new LinearLayout.LayoutParams(0,-1,58):new LinearLayout.LayoutParams(-1,-2));

        LinearLayout top = new LinearLayout(this); top.setGravity(Gravity.CENTER_VERTICAL);
        LinearLayout intro = new LinearLayout(this); intro.setOrientation(LinearLayout.VERTICAL);
        TextView title=text(getString(R.string.choose_access),28,Color.rgb(15,39,31)); title.setTypeface(Typeface.DEFAULT,Typeface.BOLD); intro.addView(title);
        TextView subtitle=text(getString(R.string.choose_access_help),15,Color.rgb(100,120,112)); subtitle.setPadding(0,dp(5),0,0); intro.addView(subtitle);
        top.addView(intro,new LinearLayout.LayoutParams(0,-2,1));
        LinearLayout languages=new LinearLayout(this); languages.setPadding(dp(5),dp(5),dp(5),dp(5)); languages.setBackground(rounded(Color.rgb(232,239,236),22,Color.TRANSPARENT,0));
        boolean portuguese=currentLanguage().equals("pt"); TextView pt=languagePill("PT",portuguese),it=languagePill("IT",!portuguese);
        languages.addView(pt,new LinearLayout.LayoutParams(dp(55),dp(38))); languages.addView(it,new LinearLayout.LayoutParams(dp(55),dp(38))); top.addView(languages); content.addView(top);
        pt.setOnClickListener(v->setLanguage("pt")); it.setOnClickListener(v->setLanguage("it"));

        LinearLayout platform=accessCard("CRM",getString(R.string.platform),getString(R.string.platform_help),v->openWeb(BuildConfig.PLATFORM_URL,getString(R.string.platform)));
        LinearLayout agenda=accessCard("08",getString(R.string.live_agenda),getString(R.string.live_agenda_help),v->openWeb(BuildConfig.PLATFORM_URL+"monitor-oficina.html?lang="+currentLanguage(),getString(R.string.live_agenda)));
        LinearLayout.LayoutParams action=new LinearLayout.LayoutParams(-1,dp(102)); action.setMargins(0,dp(22),0,0); content.addView(platform,action);
        LinearLayout.LayoutParams actionSecond=new LinearLayout.LayoutParams(-1,dp(102)); actionSecond.setMargins(0,dp(14),0,0); content.addView(agenda,actionSecond);
        TextView footer=text(getString(R.string.tablet_ready),13,Color.rgb(126,144,137)); footer.setGravity(Gravity.CENTER); footer.setPadding(0,dp(25),0,0); content.addView(footer);
    }

    private String currentLanguage() { return getSharedPreferences(PREFS,MODE_PRIVATE).getString(LANGUAGE,"pt"); }
    private void setLanguage(String language) {
        if (language.equals(currentLanguage())) return;
        getSharedPreferences(PREFS,MODE_PRIVATE).edit().putString(LANGUAGE,language).apply();
        recreate();
    }

    private void openWeb(String url, String title) {
        root.removeAllViews();
        LinearLayout shell=new LinearLayout(this); shell.setOrientation(LinearLayout.VERTICAL); root.addView(shell,new FrameLayout.LayoutParams(-1,-1));
        LinearLayout toolbar=new LinearLayout(this); toolbar.setGravity(Gravity.CENTER_VERTICAL); toolbar.setPadding(dp(8),dp(5),dp(8),dp(5)); toolbar.setBackgroundColor(Color.rgb(7,61,49));
        Button home=button("⌂ "+getString(R.string.home)); Button refresh=button("↻ "+getString(R.string.refresh));
        TextView toolbarTitle=text(title,20,Color.WHITE); toolbarTitle.setGravity(Gravity.CENTER); toolbarTitle.setTypeface(null,1);
        toolbar.addView(home,new LinearLayout.LayoutParams(-2,dp(50))); toolbar.addView(toolbarTitle,new LinearLayout.LayoutParams(0,dp(50),1)); toolbar.addView(refresh,new LinearLayout.LayoutParams(-2,dp(50))); shell.addView(toolbar);

        webView=new WebView(this); shell.addView(webView,new LinearLayout.LayoutParams(-1,0,1));
        webView.getSettings().setJavaScriptEnabled(true); webView.getSettings().setDomStorageEnabled(true); webView.getSettings().setDatabaseEnabled(false);
        webView.getSettings().setAllowFileAccess(false); webView.getSettings().setAllowContentAccess(false); webView.getSettings().setMixedContentMode(android.webkit.WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        webView.getSettings().setUserAgentString(webView.getSettings().getUserAgentString()+" E-MotogreenTablet/1.0");
        CookieManager.getInstance().setAcceptCookie(true); CookieManager.getInstance().setAcceptThirdPartyCookies(webView,false);
        webView.setWebChromeClient(new WebChromeClient());
        webView.setWebViewClient(new WebViewClient(){
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri=request.getUrl();
                if ("e-moto.motogreen.pt".equalsIgnoreCase(uri.getHost())) return false;
                startActivity(new Intent(Intent.ACTION_VIEW,uri)); return true;
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) { handler.cancel(); }
        });
        home.setOnClickListener(v->showLauncher()); refresh.setOnClickListener(v->webView.reload()); webView.loadUrl(url);
    }

    @Override public void onBackPressed() {
        if (webView != null && webView.canGoBack()) webView.goBack();
        else if (webView != null) showLauncher();
        else super.onBackPressed();
    }

    @Override protected void onDestroy() {
        if (webView != null) webView.destroy();
        super.onDestroy();
    }
}
