from django_filters.rest_framework import DjangoFilterBackend
from rest_framework import filters, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from .models import Product
from .serializers import ProductSerializer


class ProductViewSet(viewsets.ModelViewSet):
    """
    CRUD complet sur les produits du catalogue.

    Expose automatiquement : list, retrieve, create, update, partial_update, destroy.
    """

    queryset = Product.objects.all()
    serializer_class = ProductSerializer
    filter_backends = [DjangoFilterBackend, filters.SearchFilter, filters.OrderingFilter]
    filterset_fields = ['category']
    search_fields = ['name', 'description']
    ordering_fields = ['price', 'created_at', 'stock']

    @action(detail=False, methods=['get'], url_path='low-stock')
    def low_stock(self, request):
        """Bonus : liste les produits dont le stock est < 5 (réappro à prévoir)."""
        produits = self.get_queryset().filter(stock__lt=5)
        page = self.paginate_queryset(produits)
        if page is not None:
            serializer = self.get_serializer(page, many=True)
            return self.get_paginated_response(serializer.data)
        serializer = self.get_serializer(produits, many=True)
        return Response(serializer.data)
