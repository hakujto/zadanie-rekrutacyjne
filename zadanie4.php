<?php
//zad 4

// Błąd A - Problem z baza danych, nie moze odszukac tabeli, ktora prawdopodobnie istnieje ale moze byc pod inna nazwa, trzeba sprawdzic czy jest i opcjonalnie utworzyc nowa.

// Błąd B - Blokada zlozenia wniosku po za okresem zadtudnienia. Zalezy jakie zalozenia sa w projekcie ale aktualnie komunikat wyglada ok. 

// Błąd C - Jest wpisane "30B" zamiast integer, nalezy dodac walidacje aby user nie mogl podac takich danych(string zamiast integer) w tym polu, najlepiej widoczny dla niego err w procesie wypelniania danych. 

// Błąd D - kod -1 moze byc specyficzny dla sage erp fk, nalezy zbadac dokumentacje, i najlepiej zdebugowac endpointy z naszej strony aby upewnic sie czy po drodze nie ma err w kodzie.

// Błąd E - strona nie może pobrać jakiegoś pliku z serwera. To może być problem z internetem, złym adresem albo że serwer nie odpowiada. Upewnic sie czy po stronie serwera jest ok, jak tak to trzeba jeszcze zdebugowac kod.