<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios y elementos de prueba de la API común.
 *
 * Todos usan la contraseña: Maktub-2026
 * (se guarda con Hash::make(), nunca en texto plano).
 */
class ApiDemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Maktub-2026';

    public function run(): void
    {
        $usuarios = [
            'rafa' => [
                ['Chaqueta Maktub Denim', 'Talla L. Chaqueta vaquera de la colección Maktub, edición limitada.'],
                ['Camiseta algodón orgánico', 'Talla M. Camiseta negra de algodón orgánico con logo bordado.'],
            ],
            'nacho' => [
                ['Sudadera Maktub Logo', 'Talla XL. Sudadera gris con capucha y logo frontal.'],
                ['Pantalón cargo beige', 'Talla 42. Pantalón cargo con bolsillos laterales.'],
            ],
            'victor' => [
                ['Gorra Maktub', 'Talla única. Gorra negra ajustable con bordado blanco.'],
                ['Bufanda de lana', 'Talla única. Bufanda de lana merino en color burdeos.'],
            ],
            'daniel' => [
                ['Zapatillas Maktub Street', 'Talla 43. Zapatillas blancas de edición especial.'],
                ['Cinturón de cuero', 'Talla 95. Cinturón de cuero marrón con hebilla metálica.'],
            ],
        ];

        foreach ($usuarios as $username => $items) {
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => ucfirst($username),
                    'email' => $username.'@maktub.local',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => 'cliente',
                ]
            );

            foreach ($items as [$title, $text]) {
                Item::updateOrCreate(
                    ['user_id' => $user->id, 'title' => $title],
                    ['text' => $text]
                );
            }
        }
    }
}
