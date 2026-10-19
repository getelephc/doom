<?php

extern "SDL2" {
    function SDL_Init(u32 $flags): i32;
    function SDL_Quit(): void;
    function SDL_CreateWindow(string $title, i32 $x, i32 $y, i32 $w, i32 $h, u32 $flags): ptr;
    function SDL_DestroyWindow(ptr $window): void;
    function SDL_CreateRenderer(ptr $window, i32 $index, u32 $flags): ptr;
    function SDL_DestroyRenderer(ptr $renderer): void;
    function SDL_SetRenderDrawColor(ptr $renderer, u8 $r, u8 $g, u8 $b, u8 $a): i32;
    function SDL_RenderClear(ptr $renderer): i32;
    function SDL_RenderDrawLine(ptr $renderer, i32 $x1, i32 $y1, i32 $x2, i32 $y2): i32;
    function SDL_RenderDrawPoint(ptr $renderer, i32 $x, i32 $y): i32;
    function SDL_RenderPresent(ptr $renderer): void;
    function SDL_PumpEvents(): void;
    function SDL_GetKeyboardState(ptr $numkeys): ptr;
    function SDL_GetTicks(): u32;
    function SDL_Delay(u32 $ms): void;
    function SDL_GetError(): string;
}
