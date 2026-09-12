<?php
App::render('head', [
    'pageTitle'       => 'Our Story — Hazra Electrical Bike',
    'pageDescription' => 'Learn how Hazra Electrical Bike was born from one frame, one battery, and a stubborn idea to build electric scooters that riders actually want.',
    'extraCss'        => ['assets/css/styles/pages/our-story.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- HERO -->
<section class="story-hero">
  <div class="story-hero__bg" aria-hidden="true"></div>
  <div class="story-hero__veil" aria-hidden="true"></div>
  <div class="story-hero__in">
    <p class="eyebrow"><i class="sq"></i>OUR STORY</p>
    <h1 class="story-hero__title">Built in the city,<br>tuned for the long way home.</h1>
    <p class="story-hero__lead">One frame, one battery, and a stubborn idea — electric scooters that feel like something you want to ride.</p>
  </div>
</section>

<!-- ABOUT INTRO -->
<section class="story-about">
  <div class="wrap">
    <div class="story-about__grid">
      <div class="story-about__media">
        <div class="story-about__accent story-about__accent--v" aria-hidden="true"></div>
        <div class="story-about__accent story-about__accent--h" aria-hidden="true"></div>
        <div class="story-about__card" id="aboutCarousel">
          <div class="story-about__slides">
            <div class="story-about__pair is-active">
              <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=80" alt="Electric scooter on the road" loading="eager">
            </div>
            <div class="story-about__pair">
              <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=900&q=80" alt="Workshop and vehicle detail" loading="lazy">
            </div>
            <div class="story-about__pair">
              <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=80" alt="Rider travelling through the city" loading="lazy">
            </div>
            <div class="story-about__pair">
              <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=900&q=80" alt="City ride at golden hour" loading="lazy">
            </div>
            <div class="story-about__pair">
              <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=900&q=80" alt="Hazra workshop detail" loading="lazy">
            </div>
            <div class="story-about__pair">
              <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=80" alt="Scooter ready for the road" loading="lazy">
            </div>
          </div>
        </div>
        <div class="story-about__dots" role="tablist" aria-label="About Hazra images">
          <button class="is-on" type="button" aria-label="Image 1" aria-selected="true"></button>
          <button type="button" aria-label="Image 2" aria-selected="false"></button>
          <button type="button" aria-label="Image 3" aria-selected="false"></button>
          <button type="button" aria-label="Image 4" aria-selected="false"></button>
          <button type="button" aria-label="Image 5" aria-selected="false"></button>
          <button type="button" aria-label="Image 6" aria-selected="false"></button>
        </div>
      </div>
      <div class="story-about__copy">
        <div class="story-about__icon"><i data-lucide="file-pen"></i></div>
        <p class="eyebrow"><i class="sq"></i>ABOUT HAZRA</p>
        <h2 class="story-about__title">We’re <em>presently</em> building honest electric rides.</h2>
        <p class="story-about__lead">
          Hazra Electrical Bike started as one frame, one battery and a clear brief:
          make an EV that feels desirable on real Indian roads — not just on a brochure.
          Every model shares the same spine: a swappable pack, a torque map written for
          stop-start traffic, and a body pressed so there is nothing to rattle loose.
          We build them carefully, service what we sell, and publish the range figures we actually measured.
        </p>
        <a class="btn btn--brand" href="#journey">Read More <i data-lucide="arrow-right"></i></a>
      </div>
    </div>
  </div>
</section>

<!-- WHY — four reasons to choose Hazra EV -->
<section class="story-why">
  <div class="wrap">
    <header class="sec__head">
      <p class="eyebrow"><i class="sq"></i>WHY PEOPLE CHOOSE HAZRA EV</p>
      <h2 class="sec__title">The details that make<br><span class="hl">everyday riding better.</span></h2>
    </header>

    <div class="story-steps">
      <article class="story-step" style="--i:0">
        <div class="story-step__icon"><i data-lucide="gauge"></i></div>
        <h3 class="story-step__label">Honest range</h3>
        <p class="story-step__desc">Real measured kilometres, not brochure numbers. Know what your ride can do before you set out.</p>
        <span class="story-step__arrow" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
      </article>
      <article class="story-step" style="--i:1">
        <div class="story-step__icon"><i data-lucide="battery-charging"></i></div>
        <h3 class="story-step__label">Swappable battery</h3>
        <p class="story-step__desc">Lift, swap, ride. Keep moving without building your day around a charging stop.</p>
        <span class="story-step__arrow" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
      </article>
      <article class="story-step" style="--i:2">
        <div class="story-step__icon"><i data-lucide="route"></i></div>
        <h3 class="story-step__label">City-tuned ride</h3>
        <p class="story-step__desc">Torque shaped for stop-start traffic, tight streets, daily loads and the monsoon.</p>
        <span class="story-step__arrow" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
      </article>
      <article class="story-step" style="--i:3">
        <div class="story-step__icon"><i data-lucide="wrench"></i></div>
        <h3 class="story-step__label">Service that stays</h3>
        <p class="story-step__desc">A dealer and service network that remains part of ownership after the sale.</p>
      </article>
    </div>
  </div>
</section>

<!-- SCROLL-PINNED TIMELINE -->
<section class="story-tl" id="journey">
  <div class="story-tl__head wrap">
    <p class="eyebrow"><i class="sq"></i>THE JOURNEY</p>
    <h2 class="sec__title">A long look at<br><span class="hl">how we got here.</span></h2>
  </div>

  <div class="story-tl__drive" id="tlDrive">
    <div class="story-tl__pin" id="tlPin">
      <div class="story-tl__progress" aria-hidden="true"><i id="tlBar"></i></div>

      <div class="story-tl__years" aria-hidden="true">
        <div class="story-tl__year is-active" data-y="0">2014</div>
        <div class="story-tl__year" data-y="1">2018</div>
        <div class="story-tl__year" data-y="2">2022</div>
        <div class="story-tl__year" data-y="3">2026</div>
      </div>

      <div class="story-tl__stage">
        <article class="story-tl__slide is-active" data-s="0">
          <div class="story-tl__img">
            <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1200&q=80" alt="First workshop" loading="lazy">
          </div>
          <div class="story-tl__copy">
            <p class="story-tl__tag"><i></i>Chapter 01</p>
            <h3 class="story-tl__title">The first frame</h3>
            <p class="story-tl__text">
              A single prototype, a stubborn brief, and the belief that an electric scooter
              should feel like something you choose — not something you settle for.
              Research on real city routes began here.
            </p>
          </div>
        </article>

        <article class="story-tl__slide" data-s="1">
          <div class="story-tl__img">
            <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=1200&q=80" alt="City launch" loading="lazy">
          </div>
          <div class="story-tl__copy">
            <p class="story-tl__tag"><i></i>Chapter 02</p>
            <h3 class="story-tl__title">City launch</h3>
            <p class="story-tl__text">
              First production batches rolled out. Rider feedback became the product
              roadmap — torque maps, battery swaps, and service habits shaped by people
              who rode every day, not by a brochure committee.
            </p>
          </div>
        </article>

        <article class="story-tl__slide" data-s="2">
          <div class="story-tl__img">
            <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=1200&q=80" alt="Network grows" loading="lazy">
          </div>
          <div class="story-tl__copy">
            <p class="story-tl__tag"><i></i>Chapter 03</p>
            <h3 class="story-tl__title">Network grows</h3>
            <p class="story-tl__text">
              Service points expanded across cities and towns. Range claims were tightened
              to measured data. The idea scaled without losing the craft that started it.
            </p>
          </div>
        </article>

        <article class="story-tl__slide" data-s="3">
          <div class="story-tl__img">
            <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=1200&q=80" alt="CHALO family" loading="lazy">
          </div>
          <div class="story-tl__copy">
            <p class="story-tl__tag"><i></i>Chapter 04</p>
            <h3 class="story-tl__title">The CHALO line</h3>
            <p class="story-tl__text">
              A full model family — from eco city runs to high-speed daily riders —
              built on the same spine: honest range, service that stays, and a ride
              tuned for the long way home.
            </p>
          </div>
        </article>

        <div class="story-tl__dots" id="tlDots" aria-label="Timeline chapters">
          <button class="story-tl__dot is-on" type="button" data-d="0" aria-label="2014"></button>
          <button class="story-tl__dot" type="button" data-d="1" aria-label="2018"></button>
          <button class="story-tl__dot" type="button" data-d="2" aria-label="2022"></button>
          <button class="story-tl__dot" type="button" data-d="3" aria-label="2026"></button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- EXPERTISE MOSAIC -->
<section class="story-exp">
  <div class="wrap">
    <div class="story-exp__grid">
      <div>
        <p class="eyebrow"><i class="sq"></i>BY THE NUMBERS</p>
        <h2 class="sec__title">Craft you can<br><span class="hl">measure.</span></h2>
        <p class="story-exp__lead">
          From the first single-piece frame to a national service footprint,
          every step is measured by what riders actually need on the road.
        </p>
        <a class="btn btn--ink" href="<?= e(base_url('products')) ?>">
          <span>See the collection</span><i data-lucide="arrow-right"></i>
        </a>
      </div>
      <div class="story-mosaic">
        <div class="story-mosaic__cell story-mosaic__cell--tall" style="--i:0">
          <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=800&q=80" alt="On the road" loading="lazy">
        </div>
        <div class="story-mosaic__cell" style="--i:1">
          <div class="story-mosaic__stat story-mosaic__stat--reviews">
            <div class="story-faces" aria-hidden="true">
              <img src="https://i.pravatar.cc/72?u=review1" alt="">
              <img src="https://i.pravatar.cc/72?u=review2" alt="">
              <img src="https://i.pravatar.cc/72?u=review3" alt="">
            </div>
            <div class="story-mosaic__n">4.9<span class="story-mosaic__unit">/5</span></div>
            <div class="story-mosaic__stars" aria-label="4.9 out of 5 stars">★★★★★</div>
            <div class="story-mosaic__l">Google reviews</div>
            <p class="story-mosaic__d">Rated by 1,200+ riders.</p>
          </div>
        </div>
        <div class="story-mosaic__cell" style="--i:2">
          <div class="story-mosaic__stat">
            <div class="story-mosaic__n">#1</div>
            <div class="story-mosaic__l">Google rank position</div>
            <p class="story-mosaic__d">Top-rated EV scooter network.</p>
          </div>
        </div>
        <div class="story-mosaic__cell" style="--i:3">
          <div class="story-mosaic__stat story-mosaic__stat--grad">
            <div class="story-faces">
              <img src="https://i.pravatar.cc/72?u=h1" alt="">
              <img src="https://i.pravatar.cc/72?u=h2" alt="">
              <img src="https://i.pravatar.cc/72?u=h3" alt="">
            </div>
            <div class="story-mosaic__n" data-to="48" data-suffix="+">0</div>
            <div class="story-mosaic__l">Service points</div>
            <p class="story-mosaic__d">Find a dealer near you.</p>
          </div>
        </div>
        <div class="story-mosaic__cell" style="--i:4">
          <div class="story-mosaic__stat">
            <div class="story-mosaic__n" data-to="130" data-suffix=" km">0</div>
            <div class="story-mosaic__l">Best-case range</div>
            <p class="story-mosaic__d">Figures we measured, not invented.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TEAM -->
<section class="story-team" id="team">
  <div class="wrap">
    <p class="eyebrow" style="justify-content:center"><i class="sq"></i>OUR TEAM</p>
    <h2 class="sec__title">Dedicated people<br><span class="hl">behind every ride.</span></h2>
    <div class="story-team__grid">
      <article class="story-member" style="--i:0">
        <div class="story-member__photo"><img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=500&q=80" alt="Rahul Sen" loading="lazy"></div>
        <div class="story-member__info"><h3 class="story-member__name">Rahul Sen</h3><p class="story-member__role">Founder &amp; Design</p></div>
      </article>
      <article class="story-member" style="--i:1">
        <div class="story-member__photo"><img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=500&q=80" alt="Priya Das" loading="lazy"></div>
        <div class="story-member__info"><h3 class="story-member__name">Priya Das</h3><p class="story-member__role">Product Lead</p></div>
      </article>
      <article class="story-member" style="--i:2">
        <div class="story-member__photo"><img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=500&q=80" alt="Amit Roy" loading="lazy"></div>
        <div class="story-member__info"><h3 class="story-member__name">Amit Roy</h3><p class="story-member__role">Engineering</p></div>
      </article>
      <article class="story-member" style="--i:3">
        <div class="story-member__photo"><img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=500&q=80" alt="Sneha Ghosh" loading="lazy"></div>
        <div class="story-member__info"><h3 class="story-member__name">Sneha Ghosh</h3><p class="story-member__role">Customer Success</p></div>
      </article>
    </div>
  </div>
</section>

<!-- WALL OF LOVE -->
<section class="story-gal" id="wall">
  <div class="wrap">
    <header class="sec__head">
      <p class="eyebrow"><i class="sq"></i>WALL OF LOVE</p>
      <h2 class="sec__title">Gallery and<br><span class="hl">Testimonials</span></h2>
    </header>

    <div class="story-gal__grid">
      <article class="story-gal__card story-gal__card--photo" style="--i:0">
        <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=600&q=80" alt="Rider on Hazra scooter" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
        <a class="story-gal__zoom" href="#" aria-label="Zoom"><i data-lucide="maximize-2"></i></a>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:1">
        <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=600&q=80" alt="City commute" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
        <a class="story-gal__zoom" href="#" aria-label="Zoom"><i data-lucide="maximize-2"></i></a>
      </article>

      <article class="story-gal__card story-gal__card--quote" style="--i:2">
        <div class="story-gal__mark"><i data-lucide="quote"></i></div>
        <p class="story-gal__q">“Range is honest. After six months of office runs, the numbers still match what they promised at delivery.”</p>
        <div class="story-gal__who">
          <img src="https://i.pravatar.cc/80?u=s1" alt="">
          <div>
            <b>Ankit Mehta</b>
            <span class="story-gal__verified"><i data-lucide="badge-check"></i> Verified Owner</span>
            <span>CHALO Smart Pro · Delhi</span>
          </div>
        </div>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:3">
        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=80" alt="Owner portrait" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
      </article>

      <article class="story-gal__card story-gal__card--quote" style="--i:4">
        <div class="story-gal__mark"><i data-lucide="quote"></i></div>
        <p class="story-gal__q">“Service stayed after the sale. Quiet in traffic, easy to live with every single day.”</p>
        <div class="story-gal__who">
          <img src="https://i.pravatar.cc/80?u=s2" alt="">
          <div>
            <b>Neha Kapoor</b>
            <span class="story-gal__verified"><i data-lucide="badge-check"></i> Verified Owner</span>
            <span>CHALO 1000 V2 · Ghaziabad</span>
          </div>
        </div>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:5">
        <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=600&q=80" alt="Evening ride" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
        <a class="story-gal__zoom" href="#" aria-label="Zoom"><i data-lucide="maximize-2"></i></a>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:6">
        <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=600&q=80" alt="Scooter detail" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
      </article>

      <article class="story-gal__card story-gal__card--quote" style="--i:7">
        <div class="story-gal__mark"><i data-lucide="quote"></i></div>
        <p class="story-gal__q">“Swappable pack changed my week. No waiting at a charger between shifts.”</p>
        <div class="story-gal__who">
          <img src="https://i.pravatar.cc/80?u=s3" alt="">
          <div>
            <b>Vikram Singh</b>
            <span class="story-gal__verified"><i data-lucide="badge-check"></i> Verified Owner</span>
            <span>CHALO Neo · Lucknow</span>
          </div>
        </div>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:8">
        <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=600&q=80" alt="Family ride" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
        <a class="story-gal__zoom" href="#" aria-label="Zoom"><i data-lucide="maximize-2"></i></a>
      </article>

      <article class="story-gal__card story-gal__card--quote" style="--i:9">
        <div class="story-gal__mark"><i data-lucide="quote"></i></div>
        <p class="story-gal__q">“Build quality feels solid. Dealer support in my city made the switch from petrol painless.”</p>
        <div class="story-gal__who">
          <img src="https://i.pravatar.cc/80?u=s4" alt="">
          <div>
            <b>Sudipto Ghosh</b>
            <span class="story-gal__verified"><i data-lucide="badge-check"></i> Verified Owner</span>
            <span>CHALO Smart Plus · Kolkata</span>
          </div>
        </div>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:10">
        <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=80" alt="Owner" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
      </article>

      <article class="story-gal__card story-gal__card--photo" style="--i:11">
        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=80" alt="Rider" loading="lazy">
        <span class="story-gal__badge"><i data-lucide="badge-check"></i> Verified Owner</span>
        <a class="story-gal__zoom" href="#" aria-label="Zoom"><i data-lucide="maximize-2"></i></a>
      </article>
    </div>
  </div>
</section>

<!-- JOIN THE MOVEMENT + MAP -->
<section class="story-map" id="network">
  <div class="wrap">
    <div class="story-map__grid">
      <div>
        <p class="eyebrow"><i class="sq"></i> JOIN THE MOVEMENT</p>
        <h2 class="story-map__title">India’s growing<br><span>Hazra network</span></h2>
        <p class="story-map__text">
          Dealers and service points across the map — from metros to emerging cities.
          Strategic partners help us deliver scooters, spares and care closer to home.
        </p>
        <div class="story-map__partners">
          <span class="story-map__partner"><strong>●</strong> Dealers</span>
          <span class="story-map__partner"><strong>●</strong> Service</span>
          <span class="story-map__partner"><strong>●</strong> BNHS</span>
          <span class="story-map__partner"><strong>●</strong> Charge partners</span>
        </div>
        <p class="story-map__quote">“Hazra EV — Not just a Ride, a Revolution.”</p>
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
          <a class="btn btn--ink" href="<?= e(base_url('become-a-dealer')) ?>"><span>Become a dealer</span><i data-lucide="arrow-right"></i></a>
          <a class="btn btn--ghost story-map__ghost" href="<?= e(base_url('dealer-locator')) ?>">Locate dealers</a>
        </div>
      </div>

      <div class="story-map__visual">
        <img class="story-map__svg" src="<?= e(base_url('assets/ind-map.png')) ?>" alt="Hazra Electrical Bike network across India" loading="lazy">
        <div class="story-map__legend">
          <span><i></i> Delhi NCR</span>
          <span><i></i> Mumbai</span>
          <span><i></i> Bengaluru</span>
          <span><i></i> Kolkata</span>
          <span><i></i> Lucknow</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="story-cta">
  <div class="story-cta__bg" aria-hidden="true">
    <video autoplay muted loop playsinline poster="<?= e(base_url('assets/scutie_light.webp')) ?>">
      <source src="<?= e(base_url('assets/about.mp4')) ?>" type="video/mp4">
    </video>
    <div class="story-cta__veil"></div>
  </div>
  <div class="wrap">
    <h2 class="sec__title">Ready for the <span class="hl">next chapter?</span></h2>
    <p>Book a test ride or find a dealer — the road is waiting.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn--ink" href="<?= e(base_url('index#test-ride')) ?>"><span>Book test ride</span><i data-lucide="bike"></i></a>
      <a class="btn btn--ghost" href="<?= e(base_url('dealer-locator')) ?>">Locate dealers</a>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });
  document.querySelectorAll('.story-step,.story-mosaic__cell,.story-member,.story-gal__card').forEach(el => io.observe(el));

  function countUp(el) {
    const to = parseFloat(el.dataset.to || '0');
    const suffix = el.dataset.suffix || '';
    const t0 = performance.now();
    const dur = 1200;
    const tick = t => {
      const p = Math.min(1, (t - t0) / dur);
      el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))) + suffix;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }
  const co = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.querySelectorAll('[data-to]').forEach(countUp);
        co.unobserve(e.target);
      }
    });
  }, { threshold: 0.3 });
  document.querySelectorAll('.story-mosaic').forEach(el => co.observe(el));

  /* About carousel */
  (() => {
    const root = document.getElementById('aboutCarousel');
    const slides = [...(root?.querySelectorAll('.story-about__pair') || [])];
    const dots = [...(root?.parentElement?.querySelectorAll('.story-about__dots button') || [])];
    let active = 0;
    const go = i => {
      if (!slides.length) return;
      slides[active].classList.remove('is-active');
      dots[active]?.classList.remove('is-on');
      dots[active]?.setAttribute('aria-selected', 'false');
      active = (i + slides.length) % slides.length;
      slides[active].classList.add('is-active');
      dots[active]?.classList.add('is-on');
      dots[active]?.setAttribute('aria-selected', 'true');
    };
    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
    if (slides.length > 1) setInterval(() => go(active + 1), 3000);

    const videoSections = [...document.querySelectorAll('.story-why--video,.story-cta')];
    const reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;
    if (!reduce && videoSections.length) {
      let ticking = false;
      const update = () => {
        videoSections.forEach(section => {
          const video = section.querySelector('video');
          const r = section.getBoundingClientRect();
          if (!video || r.bottom < 0 || r.top > innerHeight) return;
          const p = (innerHeight - r.top) / (innerHeight + r.height);
          video.style.transform = `scale(1.08) translateY(${(p - .5) * 40}px)`;
        });
        ticking = false;
      };
      addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(update);
      }, { passive: true });
      update();
    }
  })();

  /* Scroll-pinned timeline */
  (() => {
    const drive = document.getElementById('tlDrive');
    const years = [...document.querySelectorAll('.story-tl__year')];
    const slides = [...document.querySelectorAll('.story-tl__slide')];
    const dots = [...document.querySelectorAll('.story-tl__dot')];
    const bar = document.getElementById('tlBar');
    if (!drive || !slides.length) return;

    const desktop = window.matchMedia('(min-width:961px)');
    let active = 0;

    const setActive = i => {
      i = Math.max(0, Math.min(slides.length - 1, i));
      if (i === active && years[i]?.classList.contains('is-active')) return;
      active = i;
      years.forEach((y, n) => {
        y.classList.toggle('is-active', n === i);
        y.classList.toggle('is-passed', n < i);
      });
      slides.forEach((s, n) => s.classList.toggle('is-active', n === i));
      dots.forEach((d, n) => d.classList.toggle('is-on', n === i));
    };

    const onScroll = () => {
      if (!desktop.matches) {
        slides.forEach(s => s.classList.add('is-active'));
        return;
      }
      const rect = drive.getBoundingClientRect();
      const span = drive.offsetHeight - window.innerHeight;
      const gone = Math.min(Math.max(-rect.top, 0), span);
      const p = span > 0 ? gone / span : 0;
      if (bar) bar.style.height = (p * 100) + '%';
      const idx = Math.min(slides.length - 1, Math.floor(p * slides.length));
      setActive(idx);
    };

    dots.forEach(d => {
      d.addEventListener('click', () => {
        if (!desktop.matches) return;
        const i = +d.dataset.d;
        const span = drive.offsetHeight - window.innerHeight;
        const y = drive.offsetTop + (span * (i + .15) / slides.length);
        window.scrollTo({ top: y, behavior: 'smooth' });
      });
    });

    addEventListener('scroll', onScroll, { passive: true });
    addEventListener('resize', onScroll);
    onScroll();
  })();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
