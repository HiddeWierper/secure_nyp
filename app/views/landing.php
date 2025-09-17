<?php
// Landing page voor Schoonmaakbeheer Platform - SEO geoptimaliseerd
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Schoonmaakbeheer Platform - Professioneel Taakbeheer | Efficiënt & Betrouwbaar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- SEO Meta Tags -->
  <meta name="description" content="Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven. Beheer taken, volg voortgang en optimaliseer schoonmaakprocessen met ons gebruiksvriendelijke systeem.">
  <meta name="keywords" content="schoonmaakbeheer, schoonmaakmanagement, taakbeheer, schoonmaaksysteem, digitaal schoonmaakbeheer, restaurant schoonmaak, horeca schoonmaak, bedrijfsschoonmaak, Hidde Wierper">
  <meta name="author" content="Hidde Wierper - Freelance developer">
  <meta name="robots" content="index, follow">
  <meta name="language" content="Dutch">
  <meta name="revisit-after" content="7 days">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://nypschoonmaak.nl">
  <meta property="og:title" content="Schoonmaakbeheer Platform - Professioneel Taakbeheer">
  <meta property="og:description" content="Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven. Beheer taken, volg voortgang en optimaliseer schoonmaakprocessen.">
  <meta property="og:image" content="https://nypschoonmaak.nl/assets/logo.webp">
  <meta property="og:site_name" content="Schoonmaakbeheer Platform">
  <meta property="og:locale" content="nl_NL">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="https://nypschoonmaak.nl">
  <meta name="twitter:title" content="Schoonmaakbeheer Platform - Professioneel Taakbeheer">
  <meta name="twitter:description" content="Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven. Beheer taken, volg voortgang en optimaliseer schoonmaakprocessen.">
  <meta name="twitter:image" content="https://nypschoonmaak.nl/assets/logo.webp">

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="https://nypschoonmaak.nl/assets/logo.webp">
  <link rel="apple-touch-icon" href="https://nypschoonmaak.nl/assets/logo.webp">

  <!-- Canonical URL -->
  <link rel="canonical" href="https://nypschoonmaak.nl">

  <!-- Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    "name": "Schoonmaakbeheer Platform",
    "description": "Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven",
    "url": "https://nypschoonmaak.nl",
    "applicationCategory": "BusinessApplication",
    "operatingSystem": "Web Browser",
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "EUR"
    },
    "creator": {
      "@type": "Person",
      "name": "Hidde Wierper",
      "worksFor": {
        "@type": "Organization",
        "name": "Freelance developer"
      }
    },
    "featureList": [
      "Taakbeheer en planning",
      "Real-time voortgangsmonitoring",
      "Multi-locatie ondersteuning",
      "Gebruikersrollen en toegangsbeheer",
      "Rapportage en analytics",
      "Mobiel responsive interface"
    ]
  }
  </script>

  <!-- External Resources -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  
  <!-- Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#059669',
            secondary: '#10B981',
            accent: '#F59E0B'
          }
        }
      }
    }
  </script>
  
  <style>
    * {
      transition: all 0.3s ease;
    }
    
    body {
      background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
      min-height: 100vh;
    }
    
    .glass-card {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #047857 0%, #065f46 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4);
    }
    
    .btn-secondary {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
    }
    
    .feature-card {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }
    
    .feature-card:hover {
      background: rgba(255, 255, 255, 1);
      transform: translateY(-5px);
      box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
    }
    
    .hero-animation {
      animation: float 6s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }
    
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeInUp 0.8s ease forwards;
    }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .stagger-1 { animation-delay: 0.1s; }
    .stagger-2 { animation-delay: 0.2s; }
    .stagger-3 { animation-delay: 0.3s; }
    .stagger-4 { animation-delay: 0.4s; }
    .stagger-5 { animation-delay: 0.5s; }
    .stagger-6 { animation-delay: 0.6s; }
    
    .gradient-text {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    @media (max-width: 640px) {
      .responsive-text-lg { font-size: 1rem; }
      .responsive-text-xl { font-size: 1.125rem; }
      .responsive-text-2xl { font-size: 1.25rem; }
      .responsive-text-3xl { font-size: 1.5rem; }
      .responsive-text-4xl { font-size: 1.875rem; }
      .responsive-text-5xl { font-size: 2.25rem; }
    }
  </style>
</head>

<body class="min-h-screen">
  <!-- Navigation -->
  <nav class="fixed top-0 left-0 right-0 z-50 glass-card">
    <div class="container mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-700 rounded-xl mr-3 flex items-center justify-center">
            <i class="fas fa-tasks text-white text-xl"></i>
          </div>
          <h1 class="text-xl font-bold text-gray-800">Schoonmaakbeheer</h1>
        </div>
        
        <div class="hidden md:flex items-center space-x-6">
          <a href="#features" class="text-gray-700 hover:text-green-600 font-medium">Functies</a>
          <a href="#benefits" class="text-gray-700 hover:text-green-600 font-medium">Voordelen</a>
          <a href="#how-it-works" class="text-gray-700 hover:text-green-600 font-medium">Hoe het werkt</a>
          <a href="#contact" class="text-gray-700 hover:text-green-600 font-medium">Contact</a>
        </div>
        
        <a href="<?= url('/login') ?>" class="btn-primary text-white px-6 py-2 rounded-xl font-medium">
          <i class="fas fa-sign-in-alt mr-2"></i>Inloggen
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="pt-24 pb-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="text-center mb-16">
        <div class="hero-animation mb-8">
          <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-3xl mx-auto mb-6 flex items-center justify-center shadow-2xl">
            <i class="fas fa-broom text-white text-3xl"></i>
          </div>
        </div>
        
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6 fade-in responsive-text-5xl">
          Professioneel <span class="gradient-text">Schoonmaakbeheer</span><br>
          voor Jouw Bedrijf
        </h1>

        <p class="text-xl md:text-2xl text-green-100 mb-8 max-w-3xl mx-auto fade-in stagger-1 responsive-text-xl">
          Stroomlijn je schoonmaakprocessen met ons geavanceerde taakbeheersysteem.
          Perfect voor restaurants, hotels en bedrijven van elke grootte.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center fade-in stagger-2">
          <a href="<?= url('/login') ?>" class="btn-primary text-white px-8 py-4 rounded-xl font-medium text-lg inline-flex items-center justify-center">
            <i class="fas fa-rocket mr-2"></i>Start Nu
          </a>
          <a href="#features" class="bg-white/20 backdrop-blur text-white px-8 py-4 rounded-xl font-medium text-lg hover:bg-white/30 transition-all inline-flex items-center justify-center">
            <i class="fas fa-info-circle mr-2"></i>Meer Info
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="py-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4 fade-in responsive-text-4xl">
          Krachtige Functies voor Efficiënt Beheer
        </h2>
        <p class="text-xl text-green-100 max-w-2xl mx-auto fade-in stagger-1 responsive-text-lg">
          Alles wat je nodig hebt om je schoonmaakprocessen te optimaliseren en te professionaliseren
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- Feature 1: Taakbeheer -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-1">
          <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-tasks text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Intelligent Taakbeheer</h3>
          <p class="text-gray-600 mb-4">
            Genereer automatisch schoonmaaktaken op basis van frequentie, locatie en beschikbare tijd. 
            Geen taak wordt vergeten.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Automatische taakgeneratie</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Frequentie-gebaseerde planning</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Tijdsbeheer en optimalisatie</li>
          </ul>
        </div>

        <!-- Feature 2: Multi-locatie -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-2">
          <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-map-marked-alt text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Multi-locatie Ondersteuning</h3>
          <p class="text-gray-600 mb-4">
            Beheer meerdere vestigingen vanuit één centraal dashboard.
            Perfect voor ketens, franchises en bedrijven met meerdere locaties.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Centraal beheer dashboard</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Regio-specifieke instellingen</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Locatie-gebaseerde rapportage</li>
          </ul>
        </div>

        <!-- Feature 3: Real-time Tracking -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-3">
          <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-chart-line text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Real-time Voortgang</h3>
          <p class="text-gray-600 mb-4">
            Volg de voortgang van alle taken in real-time. 
            Krijg direct inzicht in wat er gebeurt en wat er nog moet gebeuren.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Live status updates</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Voltooiingspercentages</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Prestatie analytics</li>
          </ul>
        </div>

        <!-- Feature 4: Gebruikersrollen -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-4">
          <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-users-cog text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Geavanceerd Rechtenbeheer</h3>
          <p class="text-gray-600 mb-4">
            Verschillende gebruikersrollen met specifieke rechten en toegang. 
            Van manager tot regiomanager tot admin.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Rol-gebaseerde toegang</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Hiërarchische structuur</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Veilige authenticatie</li>
          </ul>
        </div>

        <!-- Feature 5: Rapportage -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-5">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-chart-pie text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Uitgebreide Rapportage</h3>
          <p class="text-gray-600 mb-4">
            Genereer gedetailleerde rapporten over prestaties, voltooiing en trends. 
            Data-gedreven beslissingen maken.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Prestatie dashboards</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Trend analyses</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Export mogelijkheden</li>
          </ul>
        </div>

        <!-- Feature 6: Mobiel Responsive -->
        <div class="feature-card rounded-2xl p-6 fade-in stagger-6">
          <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-teal-600 rounded-2xl mb-6 flex items-center justify-center">
            <i class="fas fa-mobile-alt text-white text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Mobiel Geoptimaliseerd</h3>
          <p class="text-gray-600 mb-4">
            Volledig responsive design dat perfect werkt op alle apparaten. 
            Beheer taken onderweg met je smartphone of tablet.
          </p>
          <ul class="text-sm text-gray-600 space-y-2">
            <li><i class="fas fa-check text-green-500 mr-2"></i>Responsive design</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Touch-vriendelijke interface</li>
            <li><i class="fas fa-check text-green-500 mr-2"></i>Offline functionaliteit</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Benefits Section -->
  <section id="benefits" class="py-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="glass-card rounded-3xl p-8 md:p-12">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4 fade-in responsive-text-4xl">
            Waarom Kiezen voor NYP Schoonmaak?
          </h2>
          <p class="text-xl text-gray-600 max-w-2xl mx-auto fade-in stagger-1 responsive-text-lg">
            Ontdek de voordelen die ons platform biedt voor jouw schoonmaakbeheer
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="space-y-6">
            <div class="flex items-start fade-in stagger-1">
              <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-clock text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Tijdsbesparing tot 60%</h3>
                <p class="text-gray-600">Automatiseer je schoonmaakplanning en bespaar uren per week aan administratie.</p>
              </div>
            </div>

            <div class="flex items-start fade-in stagger-2">
              <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-shield-alt text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">100% Betrouwbaar</h3>
                <p class="text-gray-600">Geen gemiste taken meer. Ons systeem zorgt ervoor dat alles volgens planning verloopt.</p>
              </div>
            </div>

            <div class="flex items-start fade-in stagger-3">
              <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-chart-line text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Verhoogde Efficiëntie</h3>
                <p class="text-gray-600">Optimaliseer je schoonmaakprocessen en verhoog de productiviteit van je team.</p>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div class="flex items-start fade-in stagger-4">
              <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-eye text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Volledige Transparantie</h3>
                <p class="text-gray-600">Krijg real-time inzicht in alle schoonmaakactiviteiten en prestaties.</p>
              </div>
            </div>

            <div class="flex items-start fade-in stagger-5">
              <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-cogs text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Eenvoudig te Gebruiken</h3>
                <p class="text-gray-600">Intuïtieve interface die iedereen snel onder de knie heeft, zonder training.</p>
              </div>
            </div>

            <div class="flex items-start fade-in stagger-6">
              <div class="w-12 h-12 bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl mr-4 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-headset text-white"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Persoonlijke Support</h3>
                <p class="text-gray-600">Directe ondersteuning van onze experts wanneer je het nodig hebt.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works Section -->
  <section id="how-it-works" class="py-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4 fade-in responsive-text-4xl">
          Hoe Het Werkt
        </h2>
        <p class="text-xl text-green-100 max-w-2xl mx-auto fade-in stagger-1 responsive-text-lg">
          In drie eenvoudige stappen naar geoptimaliseerd schoonmaakbeheer
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Step 1 -->
        <div class="text-center fade-in stagger-1">
          <div class="glass-card rounded-2xl p-8 mb-6">
            <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-full mx-auto mb-6 flex items-center justify-center">
              <span class="text-2xl font-bold text-white">1</span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-4">Setup & Configuratie</h3>
            <p class="text-gray-600">
              Voeg je locaties toe, stel gebruikersrollen in en configureer je schoonmaaktaken. 
              Onze wizard helpt je door het proces.
            </p>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="text-center fade-in stagger-2">
          <div class="glass-card rounded-2xl p-8 mb-6">
            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full mx-auto mb-6 flex items-center justify-center">
              <span class="text-2xl font-bold text-white">2</span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-4">Automatische Planning</h3>
            <p class="text-gray-600">
              Het systeem genereert automatisch taken op basis van frequentie, beschikbare tijd en prioriteit. 
              Geen handmatige planning meer nodig.
            </p>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="text-center fade-in stagger-3">
          <div class="glass-card rounded-2xl p-8 mb-6">
            <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full mx-auto mb-6 flex items-center justify-center">
              <span class="text-2xl font-bold text-white">3</span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-4">Uitvoeren & Monitoren</h3>
            <p class="text-gray-600">
              Medewerkers voeren taken uit en vinken ze af. Managers krijgen real-time inzicht in voortgang 
              en kunnen bijsturen waar nodig.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Statistics Section -->
  <section class="py-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="glass-card rounded-3xl p-8 md:p-12">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4 fade-in responsive-text-4xl">
            Bewezen Resultaten
          </h2>
          <p class="text-xl text-gray-600 fade-in stagger-1 responsive-text-lg">
            Cijfers die spreken voor zich
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div class="text-center fade-in stagger-1">
            <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <i class="fas fa-store text-white text-2xl"></i>
            </div>
            <div class="text-3xl font-bold text-gray-800 mb-2">50+</div>
            <div class="text-gray-600">Actieve Locaties</div>
          </div>

          <div class="text-center fade-in stagger-2">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <i class="fas fa-tasks text-white text-2xl"></i>
            </div>
            <div class="text-3xl font-bold text-gray-800 mb-2">10K+</div>
            <div class="text-gray-600">Taken per Maand</div>
          </div>

          <div class="text-center fade-in stagger-3">
            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <i class="fas fa-clock text-white text-2xl"></i>
            </div>
            <div class="text-3xl font-bold text-gray-800 mb-2">60%</div>
            <div class="text-gray-600">Tijdsbesparing</div>
          </div>

          <div class="text-center fade-in stagger-4">
            <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <i class="fas fa-chart-line text-white text-2xl"></i>
            </div>
            <div class="text-3xl font-bold text-gray-800 mb-2">95%</div>
            <div class="text-gray-600">Voltooiingspercentage</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="py-16 px-4">
    <div class="container mx-auto max-w-4xl text-center">
      <div class="glass-card rounded-3xl p-8 md:p-12">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6 fade-in responsive-text-4xl">
          Klaar om te Beginnen?
        </h2>
        <p class="text-xl text-gray-600 mb-8 fade-in stagger-1 responsive-text-lg">
          Start vandaag nog met het optimaliseren van je schoonmaakprocessen.
          Ook een op maat schoonmaakbeheer systeem nodig? Contacteer ons voor een persoonlijke oplossing.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center fade-in stagger-2">
          <a href="<?= url('/login') ?>" class="btn-primary text-white px-8 py-4 rounded-xl font-medium text-lg inline-flex items-center justify-center">
            <i class="fas fa-rocket mr-2"></i>Direct Beginnen
          </a>
          <a href="#contact" class="btn-secondary text-white px-8 py-4 rounded-xl font-medium text-lg inline-flex items-center justify-center">
            <i class="fas fa-phone mr-2"></i>Op Maat Oplossing
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section id="contact" class="py-16 px-4">
    <div class="container mx-auto max-w-6xl">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4 fade-in responsive-text-4xl">
          Contact & Ondersteuning
        </h2>
        <p class="text-xl text-green-100 fade-in stagger-1 responsive-text-lg">
          Vragen? We helpen je graag verder
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Contact Info -->
        <div class="glass-card rounded-2xl p-6 text-center fade-in stagger-1">
          <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
            <i class="fas fa-envelope text-white text-2xl"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800 mb-2">Email Support</h3>
          <p class="text-gray-600 mb-4">Voor vragen en op maat oplossingen</p>
          <a href="mailto:support@nypschoonmaak.nl" class="text-green-600 hover:text-green-700 font-medium">
            support@nypschoonmaak.nl
          </a>
        </div>

        <!-- Developer Info -->
        <div class="glass-card rounded-2xl p-6 text-center fade-in stagger-2">
          <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
            <i class="fas fa-code text-white text-2xl"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800 mb-2">Op Maat Ontwikkeling</h3>
          <p class="text-gray-600 mb-4">Hidde Wierper</p>
          <p class="text-sm text-gray-500">Freelance developer - Op maat schoonmaakbeheer systemen</p>
        </div>

        <!-- Documentation -->
        <div class="glass-card rounded-2xl p-6 text-center fade-in stagger-3">
          <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl mx-auto mb-4 flex items-center justify-center">
            <i class="fas fa-book text-white text-2xl"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800 mb-2">Documentatie</h3>
          <p class="text-gray-600 mb-4">Handleidingen en tutorials</p>
          <a href="" class="text-purple-600 hover:text-purple-700 font-medium">
            Bekijk Documentatie
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-12 px-4 border-t border-white/20">
    <div class="container mx-auto max-w-6xl">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        <!-- Company Info -->
        <div class="md:col-span-2">
          <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-700 rounded-xl mr-3 flex items-center justify-center">
              <i class="fas fa-tasks text-white text-xl"></i>
            </div>
            <h3 class="text-xl font-bold text-white">Schoonmaakbeheer</h3>
          </div>
          <p class="text-green-100 mb-4 max-w-md">
            Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven.
            Efficiënt, betrouwbaar en gebruiksvriendelijk. Ook op maat oplossingen beschikbaar.
          </p>
          <p class="text-sm text-green-200">
            © 2025 Schoonmaakbeheer Platform - Freelance developer<br>
            Ontwikkeld door Hidde Wierper
          </p>
        </div>

        <!-- Quick Links -->
        <div>
          <h4 class="text-lg font-bold text-white mb-4">Quick Links</h4>
          <ul class="space-y-2 text-green-100">
            <li><a href="#features" class="hover:text-white transition-colors">Functies</a></li>
            <li><a href="#benefits" class="hover:text-white transition-colors">Voordelen</a></li>
            <li><a href="#how-it-works" class="hover:text-white transition-colors">Hoe het werkt</a></li>
            <li><a href="<?= url('/login') ?>" class="hover:text-white transition-colors">Inloggen</a></li>
          </ul>
        </div>

        <!-- Support -->
        <div>
          <h4 class="text-lg font-bold text-white mb-4">Ondersteuning</h4>
          <ul class="space-y-2 text-green-100">
            <li><a href="#contact" class="hover:text-white transition-colors">Contact</a></li>
            <li><a href="<?= url('/docs') ?>" class="hover:text-white transition-colors">Documentatie</a></li>
            <li><a href="<?= url('/privacy') ?>" class="hover:text-white transition-colors">Privacy</a></li>
            <li><a href="<?= url('/terms') ?>" class="hover:text-white transition-colors">Voorwaarden</a></li>
          </ul>
        </div>
      </div>
    </div>
  </footer>

  <!-- Smooth Scrolling Script -->
  <script>
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Intersection Observer for fade-in animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
        }
      });
    }, observerOptions);

    // Observe all fade-in elements
    document.querySelectorAll('.fade-in').forEach(el => {
      el.style.animationPlayState = 'paused';
      observer.observe(el);
    });

    // Mobile menu toggle (if needed)
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuToggle && mobileMenu) {
      mobileMenuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
      });
    }
  </script>

  <!-- Schema.org structured data for better SEO -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Schoonmaakbeheer Platform",
    "url": "https://nypschoonmaak.nl",
    "description": "Professioneel schoonmaakbeheer platform voor restaurants, hotels en bedrijven",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://nypschoonmaak.nl/search?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
</body>
</html>