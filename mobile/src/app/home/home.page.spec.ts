import { provideHttpClient } from '@angular/common/http';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe, provideTranslateService } from '@ngx-translate/core';

import { HomePage } from './home.page';

describe('HomePage', () => {
  let component: HomePage;
  let fixture: ComponentFixture<HomePage>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [HomePage],
      // The template uses the `translate` pipe; in v18 it is standalone, so it is imported directly.
      imports: [IonicModule.forRoot(), TranslatePipe],
      providers: [provideHttpClient(), provideTranslateService({ fallbackLang: 'en', lang: 'en' })],
    }).compileComponents();

    fixture = TestBed.createComponent(HomePage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
