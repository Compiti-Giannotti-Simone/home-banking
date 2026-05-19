import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly baseUrl = '/api';

  constructor(private http: HttpClient) {}

  get<T>(path: string, params?: Record<string, string | number | boolean>): Observable<T> {
    const httpParams = params ? new HttpParams({ fromObject: params as Record<string, string> }) : undefined;
    return this.http.get<T>(`${this.baseUrl}${path}`, {
      withCredentials: true,
      params: httpParams
    });
  }

  post<T>(path: string, body: unknown): Observable<T> {
    return this.http.post<T>(`${this.baseUrl}${path}`, body, { withCredentials: true });
  }

  put<T>(path: string, body: unknown): Observable<T> {
    return this.http.put<T>(`${this.baseUrl}${path}`, body, { withCredentials: true });
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(`${this.baseUrl}${path}`, { withCredentials: true });
  }
}
