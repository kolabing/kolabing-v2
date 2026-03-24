<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case FoodProduct = 'food_product';
    case Beverage = 'beverage';
    case HealthBeauty = 'health_beauty';
    case SportsEquipment = 'sports_equipment';
    case Fashion = 'fashion';
    case TechGadget = 'tech_gadget';
    case ExperienceService = 'experience_service';
    case Other = 'other';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
