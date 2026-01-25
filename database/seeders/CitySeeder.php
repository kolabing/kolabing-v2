<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Seed the cities table with all major Spanish cities.
     *
     * Cities are organized by autonomous community and include:
     * - All provincial capitals
     * - Major tourist destinations
     * - Important business centers
     */
    public function run(): void
    {
        $cities = $this->getSpanishCities();

        foreach ($cities as $city) {
            City::query()->updateOrCreate(
                ['name' => $city['name']],
                $city
            );
        }
    }

    /**
     * Get all Spanish cities organized by autonomous community.
     *
     * @return array<int, array{name: string, country: string}>
     */
    private function getSpanishCities(): array
    {
        $cities = [];

        // Andalucia (8 provinces)
        $andalucia = [
            'Sevilla',          // Capital of Andalucia, provincial capital
            'Malaga',           // Provincial capital, Costa del Sol
            'Granada',          // Provincial capital, Alhambra
            'Cordoba',          // Provincial capital, Mezquita
            'Cadiz',            // Provincial capital, historic port
            'Almeria',          // Provincial capital
            'Huelva',           // Provincial capital
            'Jaen',             // Provincial capital
            'Marbella',         // Major tourist destination
            'Jerez de la Frontera', // Sherry wine region
            'Algeciras',        // Major port city
            'Ronda',            // Tourist destination
            'Estepona',         // Costa del Sol
            'Torremolinos',     // Costa del Sol
            'Benalmadena',      // Costa del Sol
            'Fuengirola',       // Costa del Sol
        ];

        // Cataluna (4 provinces)
        $cataluna = [
            'Barcelona',        // Capital of Catalonia, provincial capital
            'Tarragona',        // Provincial capital
            'Girona',           // Provincial capital
            'Lleida',           // Provincial capital
            'Hospitalet de Llobregat', // Barcelona metro
            'Badalona',         // Barcelona metro
            'Sabadell',         // Barcelona metro
            'Terrassa',         // Barcelona metro
            'Mataro',           // Costa del Maresme
            'Sitges',           // Tourist destination
            'Figueres',         // Dali Museum
            'Reus',             // Tarragona province
        ];

        // Comunidad de Madrid
        $madrid = [
            'Madrid',           // Capital of Spain
            'Alcala de Henares', // UNESCO World Heritage
            'Getafe',           // Madrid metro
            'Leganes',          // Madrid metro
            'Alcorcon',         // Madrid metro
            'Mostoles',         // Madrid metro
            'Fuenlabrada',      // Madrid metro
            'Torrejon de Ardoz', // Madrid metro
            'Alcobendas',       // Business district
            'Las Rozas',        // Business district
            'Pozuelo de Alarcon', // Madrid metro
        ];

        // Comunidad Valenciana (3 provinces)
        $valencia = [
            'Valencia',         // Capital of Valencia, provincial capital
            'Alicante',         // Provincial capital
            'Castellon de la Plana', // Provincial capital
            'Elche',            // Palm grove UNESCO
            'Torrevieja',       // Costa Blanca
            'Benidorm',         // Major tourist destination
            'Gandia',           // Tourist destination
            'Denia',            // Costa Blanca
            'Sagunto',          // Historic city
            'Alcoy',            // Industrial city
            'Orihuela',         // Costa Blanca
        ];

        // Pais Vasco / Euskadi (3 provinces)
        $paisVasco = [
            'Bilbao',           // Capital of Bizkaia, Guggenheim
            'San Sebastian',    // Capital of Gipuzkoa
            'Vitoria-Gasteiz',  // Capital of Alava and Euskadi
            'Barakaldo',        // Bilbao metro
            'Getxo',            // Bilbao metro
            'Irun',             // Border city
            'Eibar',            // Industrial city
            'Zarautz',          // Surf destination
        ];

        // Galicia (4 provinces)
        $galicia = [
            'Santiago de Compostela', // Capital of Galicia, pilgrimage
            'A Coruna',         // Provincial capital
            'Vigo',             // Largest city, major port
            'Ourense',          // Provincial capital
            'Lugo',             // Provincial capital, Roman walls
            'Pontevedra',       // Provincial capital
            'Ferrol',           // Naval base
        ];

        // Castilla y Leon (9 provinces)
        $castillaLeon = [
            'Valladolid',       // Capital of Castilla y Leon
            'Salamanca',        // Provincial capital, university city
            'Burgos',           // Provincial capital, cathedral
            'Leon',             // Provincial capital
            'Segovia',          // Provincial capital, aqueduct
            'Avila',            // Provincial capital, walls
            'Zamora',           // Provincial capital
            'Palencia',         // Provincial capital
            'Soria',            // Provincial capital
        ];

        // Castilla-La Mancha (5 provinces)
        $castillaMancha = [
            'Toledo',           // Capital of Castilla-La Mancha
            'Albacete',         // Provincial capital
            'Ciudad Real',      // Provincial capital
            'Guadalajara',      // Provincial capital
            'Cuenca',           // Provincial capital, hanging houses
            'Talavera de la Reina', // Major city
            'Puertollano',      // Industrial city
        ];

        // Aragon (3 provinces)
        $aragon = [
            'Zaragoza',         // Capital of Aragon
            'Huesca',           // Provincial capital, Pyrenees
            'Teruel',           // Provincial capital
            'Jaca',             // Pyrenees, winter sports
        ];

        // Islas Baleares
        $baleares = [
            'Palma',            // Capital of Balearic Islands
            'Ibiza',            // Tourist destination
            'Manacor',          // Mallorca
            'Mahon',            // Capital of Menorca
            'Ciutadella',       // Menorca
            'Santa Eulalia del Rio', // Ibiza
            'Formentera',       // Island
        ];

        // Islas Canarias (2 provinces)
        $canarias = [
            'Las Palmas de Gran Canaria', // Capital (shared), Gran Canaria
            'Santa Cruz de Tenerife', // Capital (shared), Tenerife
            'San Cristobal de La Laguna', // UNESCO, Tenerife
            'Arrecife',         // Capital of Lanzarote
            'Puerto del Rosario', // Capital of Fuerteventura
            'Puerto de la Cruz', // Tenerife tourist
            'Adeje',            // Tenerife tourist
            'Maspalomas',       // Gran Canaria tourist
            'Santa Cruz de La Palma', // La Palma
            'San Sebastian de La Gomera', // La Gomera
        ];

        // Murcia (Region)
        $murcia = [
            'Murcia',           // Capital of Region of Murcia
            'Cartagena',        // Major port, naval base
            'Lorca',            // Historic city
            'Aguilas',          // Coastal city
        ];

        // Navarra
        $navarra = [
            'Pamplona',         // Capital of Navarra, San Fermin
            'Tudela',           // Second largest city
            'Estella',          // Camino de Santiago
        ];

        // La Rioja
        $laRioja = [
            'Logrono',          // Capital of La Rioja
            'Calahorra',        // Second largest city
            'Haro',             // Wine capital
        ];

        // Extremadura (2 provinces)
        $extremadura = [
            'Merida',           // Capital of Extremadura, Roman ruins
            'Badajoz',          // Provincial capital
            'Caceres',          // Provincial capital, UNESCO
            'Plasencia',        // Historic city
        ];

        // Cantabria
        $cantabria = [
            'Santander',        // Capital of Cantabria
            'Torrelavega',      // Second largest city
            'Castro Urdiales',  // Coastal city
            'Laredo',           // Tourist destination
        ];

        // Asturias
        $asturias = [
            'Oviedo',           // Capital of Asturias
            'Gijon',            // Largest city, port
            'Aviles',           // Industrial city
            'Llanes',           // Tourist destination
        ];

        // Ceuta y Melilla (Autonomous cities)
        $ciudadesAutonomas = [
            'Ceuta',            // Autonomous city in North Africa
            'Melilla',          // Autonomous city in North Africa
        ];

        // Combine all regions
        $allCities = array_merge(
            $andalucia,
            $cataluna,
            $madrid,
            $valencia,
            $paisVasco,
            $galicia,
            $castillaLeon,
            $castillaMancha,
            $aragon,
            $baleares,
            $canarias,
            $murcia,
            $navarra,
            $laRioja,
            $extremadura,
            $cantabria,
            $asturias,
            $ciudadesAutonomas
        );

        // Format cities with country
        foreach ($allCities as $cityName) {
            $cities[] = [
                'name' => $cityName,
                'country' => 'Spain',
            ];
        }

        return $cities;
    }
}
