import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { RouteReuseStrategy } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';

import { IonicModule, IonicRouteStrategy } from '@ionic/angular';
import { provideTranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';

import { AppComponent } from './app.component';
import { AppRoutingModule } from './app-routing.module';

@NgModule({
  declarations: [AppComponent],
  imports: [BrowserModule, IonicModule.forRoot(), AppRoutingModule],
  providers: [
    { provide: RouteReuseStrategy, useClass: IonicRouteStrategy },
    provideHttpClient(),
    // Translations load from src/assets/i18n/{fr,en}.json — GENERATED from the shared i18n
    // source (i18n/source/*.json) by `npm run i18n:build`. EN is the default and the fallback,
    // matching the backend's APP_LOCALE; the LocaleService switches at runtime once the device
    // locale is detected or the user picks. enforceLoading makes a missing file fail loudly.
    provideTranslateHttpLoader({
      prefix: './assets/i18n/',
      suffix: '.json',
      enforceLoading: true,
    }),
    provideTranslateService({ fallbackLang: 'en', lang: 'en' }),
  ],
  bootstrap: [AppComponent],
})
export class AppModule {}
