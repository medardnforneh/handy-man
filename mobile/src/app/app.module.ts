import { NgModule, isDevMode } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { RouteReuseStrategy } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';

import { IonicModule, IonicRouteStrategy } from '@ionic/angular';
import { provideTranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';

import { Capacitor } from '@capacitor/core';
import { ServiceWorkerModule } from '@angular/service-worker';
import { AppComponent } from './app.component';
import { AppRoutingModule } from './app-routing.module';

@NgModule({
  declarations: [AppComponent],
  imports: [
    BrowserModule,
    IonicModule.forRoot(),
    AppRoutingModule,
    // The service worker is what makes the PWA target work offline at all (P5-01): without it the
    // browser has no app to load when the network is down, so P5-02's cached data and queued writes
    // would be unreachable — you cannot queue a message in an app that won't open.
    //
    // Disabled on NATIVE deliberately: an installed Android/iOS build already serves its assets from
    // the device, so a service worker adds a second, slower cache in front of local files and an
    // update lifecycle that fights the app store's.
    ServiceWorkerModule.register('ngsw-worker.js', {
      enabled: !isDevMode() && !Capacitor.isNativePlatform(),
      // Register once the app is stable (or after 30s) so installing it never competes with the
      // first paint on a slow device.
      registrationStrategy: 'registerWhenStable:30000',
    }),
  ],
  providers: [
    { provide: RouteReuseStrategy, useClass: IonicRouteStrategy },
    provideHttpClient(),
    // Translations load from src/assets/i18n/{fr,en}.json — GENERATED from the shared i18n
    // source (i18n/source/*.json) by `npm run i18n:build`. EN is the default and the fallback,
    // matching the backend's APP_LOCALE; the LocaleService switches at runtime once the device
    // locale is detected or the user picks. enforceLoading makes a missing file fail loudly.
    //
    // The HTTP loader MUST be passed into provideTranslateService via `loader`: called on its own,
    // provideTranslateService registers a no-op loader for the TranslateLoader token, which (being
    // declared last) would override provideTranslateHttpLoader and leave every key untranslated.
    provideTranslateService({
      loader: provideTranslateHttpLoader({
        prefix: './assets/i18n/',
        suffix: '.json',
        enforceLoading: true,
      }),
      fallbackLang: 'en',
      lang: 'en',
    }),
  ],
  bootstrap: [AppComponent],
})
export class AppModule {}
