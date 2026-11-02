@extends('layouts.public')
@section('title',$proforma->number)
@section('content') @include('proformas._document',['proforma'=>$proforma]) @endsection
