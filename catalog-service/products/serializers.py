from rest_framework import serializers

from .models import Product


class ProductSerializer(serializers.ModelSerializer):
    class Meta:
        model = Product
        fields = [
            'id', 'name', 'description', 'price',
            'stock', 'category', 'created_at', 'updated_at',
        ]
        read_only_fields = ['id', 'created_at', 'updated_at']

    def validate_price(self, value):
        if value < 0:
            raise serializers.ValidationError('Le prix ne peut pas être négatif.')
        return value

    def validate_name(self, value):
        if not value.strip():
            raise serializers.ValidationError('Le nom est obligatoire.')
        return value
